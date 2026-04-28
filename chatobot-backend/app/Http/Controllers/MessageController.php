<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Services\AI\AIFactory;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\VectorSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Extract text from a PDF file using native PHP.
     * This is a lightweight extractor — no composer dependency needed.
     */
    private function extractPdfText(string $storagePath): string
    {
        $content = Storage::get($storagePath);
        if (!$content) return '';

        // Attempt to extract text streams from PDF
        $text = '';

        // Method 1: Extract text between BT and ET markers (text objects)
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                // Extract text within parentheses (literal strings)
                if (preg_match_all('/\((.*?)\)/s', $block, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . "\n";
                }
            }
        }

        // Method 2: Decompress FlateDecode streams and extract text
        if (empty(trim($text)) && preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if ($decoded !== false) {
                    if (preg_match_all('/\((.*?)\)/s', $decoded, $textMatches)) {
                        $text .= implode(' ', $textMatches[1]) . "\n";
                    }
                }
            }
        }

        // Clean up the extracted text
        $text = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    public function store(Request $request, $chatUuid)
    {
        $request->validate([
            'content' => 'nullable|string',
            'provider' => 'nullable|string|in:openai,claude,gemini,ollama',
            'images.*' => 'nullable|file|max:10240',
            'files.*' => 'nullable|file|mimes:pdf|max:20480',
            'use_rag' => 'nullable|boolean',
        ]);

        $attachments = [];
        $pdfTexts = [];

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('public/attachments');
                $attachments[] = [
                    'type' => 'image',
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        // Handle PDF uploads
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('public/attachments');
                $attachments[] = [
                    'type' => 'pdf',
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                ];

                // Extract text from PDF
                $extractedText = $this->extractPdfText($path);
                if (!empty($extractedText)) {
                    $pdfTexts[] = "--- Content from PDF: {$file->getClientOriginalName()} ---\n{$extractedText}\n--- End of PDF ---";
                }
            }
        }

        $chat = $request->user()->chats()->findOrFail($chatUuid);
        $provider = $request->input('provider', 'ollama');

        // Save ONLY the original user text to the database (no PDF binary)
        $originalContent = $request->input('content', '');

        $userMessage = $chat->messages()->create([
            'role' => 'user',
            'content' => $originalContent,
            'attachments' => empty($attachments) ? null : $attachments,
            'provider' => $provider,
        ]);

        // RAG: Retrieve relevant document chunks if enabled
        $useRag = filter_var($request->input('use_rag', true), FILTER_VALIDATE_BOOLEAN);
        $ragContext = '';
        $ragCitations = [];

        if ($useRag && !empty($originalContent)) {
            try {
                $embeddingService = new EmbeddingService();
                $searchService = new VectorSearchService($embeddingService);
                $relevantChunks = $searchService->search($originalContent, topK: 5);

                if ($relevantChunks->isNotEmpty()) {
                    $ragContext = $searchService->formatContext($relevantChunks);
                    $ragCitations = $searchService->getCitations($relevantChunks);
                }
            } catch (\Exception $e) {
                // RAG failure should not block the response — log and continue
                \Illuminate\Support\Facades\Log::warning('RAG search failed: ' . $e->getMessage());
            }
        }

        // Build context with sliding window, then inject PDF text into last user message only
        $allMessages = $chat->messages()->orderBy('created_at', 'asc')->get();
        $messagesContext = $this->buildContext($allMessages, $pdfTexts, $ragContext);

        $aiService = AIFactory::make($provider);

        return response()->stream(function () use ($aiService, $messagesContext, $chat, $provider, $ragCitations) {
            $fullContent = '';
            $usedProvider = $provider;
            try {
                try {
                    foreach ($aiService->stream($messagesContext) as $chunk) {
                        echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                        if (ob_get_level() > 0) { ob_flush(); }
                        flush();
                        $fullContent .= $chunk;
                    }
                } catch (\Exception $e) {
                    if ($provider === 'ollama' && empty($fullContent)) {
                        $usedProvider = 'openai';
                        $fallbackService = AIFactory::make('openai');
                        foreach ($fallbackService->stream($messagesContext) as $chunk) {
                            echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                            $fullContent .= $chunk;
                        }
                    } else {
                        throw $e;
                    }
                }

                if ($fullContent !== '') {
                    $chat->messages()->create([
                        'role' => 'assistant',
                        'content' => $fullContent,
                        'provider' => $usedProvider,
                    ]);
                }

                // Send metadata about which provider actually answered + RAG citations
                $meta = ['provider' => $usedProvider];
                if (!empty($ragCitations)) {
                    $meta['rag_sources'] = $ragCitations;
                }
                echo "data: " . json_encode(['meta' => $meta]) . "\n\n";

            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            }

            echo "event: close\ndata: [DONE]\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Content-Type' => 'text/event-stream',
        ]);
    }

    /**
     * Build an optimized context window with system prompt and sliding window.
     * PDF text is injected into the last user message content for the AI only —
     * it is NOT stored in the database, so chat history stays clean.
     * RAG context is injected into the system prompt when available.
     */
    private function buildContext($allMessages, array $pdfTexts = [], string $ragContext = '')
    {
        // Build system prompt — enhance with RAG context when available
        $basePrompt = "You are Nexus AI, a highly capable personal assistant for software engineers. " .
            "You help with coding, debugging, architecture, code reviews, and general software development questions. " .
            "Be concise but thorough. Use markdown formatting for code and structured output. " .
            "When showing code, always specify the language for proper syntax highlighting. " .
            "When PDF content is provided, summarize or answer questions based on its content accurately.";

        if (!empty($ragContext)) {
            $basePrompt .= "\n\nYou have access to internal company documentation below. " .
                "When answering, prioritize information from these documents. " .
                "If the answer is found in the documents, cite the source document name. " .
                "If the documents don't contain relevant information, answer from your general knowledge " .
                "but mention that the answer is not from company docs.\n\n" . $ragContext;
        }

        $systemPrompt = new \stdClass();
        $systemPrompt->role = 'system';
        $systemPrompt->content = $basePrompt;
        $systemPrompt->attachments = null;

        // Use a sliding window: keep the first message + last 20 messages
        $maxMessages = 20;
        $messages = $allMessages->values();
        $count = $messages->count();

        if ($count <= $maxMessages) {
            $contextMessages = $messages;
        } else {
            $first = $messages->slice(0, 1);
            $last = $messages->slice($count - ($maxMessages - 1));
            $contextMessages = $first->merge($last);
        }

        // If PDF texts exist, inject them into the last user message (AI context only)
        if (!empty($pdfTexts)) {
            $contextMessages = $contextMessages->map(function ($msg, $key) use ($contextMessages, $pdfTexts) {
                if ($key === $contextMessages->keys()->last() && $msg->role === 'user') {
                    $clone = clone $msg;
                    $clone->content = $msg->content . "\n\n" . implode("\n\n", $pdfTexts);
                    return $clone;
                }
                return $msg;
            });
        }

        // Prepend system prompt
        return collect([$systemPrompt])->merge($contextMessages);
    }

    public function storeTemporary(Request $request)
    {
        $request->validate([
            'messages' => 'required|string', // Frontend will JSON stringify because of FormData
            'provider' => 'nullable|string|in:openai,claude,gemini,ollama',
            'images.*' => 'nullable|file|max:10240',
            'files.*' => 'nullable|file|mimes:pdf|max:20480',
            'use_rag' => 'nullable|boolean',
        ]);

        $provider = $request->input('provider', 'ollama');
        $rawMessages = json_decode($request->input('messages', '[]'), true);
        
        $messages = collect($rawMessages)->map(function($msg) {
            $obj = new \stdClass();
            $obj->role = $msg['role'];
            $obj->content = $msg['content'];
            $obj->attachments = null;
            return $obj;
        });

        $pdfTexts = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                // In temporary chat, we might not want to save the file, but we need to extract text.
                // We'll store it temporarily, extract, then delete.
                $path = $file->store('public/temp_attachments');
                $extractedText = $this->extractPdfText($path);
                if (!empty($extractedText)) {
                    $pdfTexts[] = "--- Content from PDF: {$file->getClientOriginalName()} ---\n{$extractedText}\n--- End of PDF ---";
                }
                \Illuminate\Support\Facades\Storage::delete($path);
            }
        }

        // RAG: Retrieve relevant document chunks for temporary chats too
        $useRag = filter_var($request->input('use_rag', true), FILTER_VALIDATE_BOOLEAN);
        $ragContext = '';
        $ragCitations = [];

        if ($useRag) {
            // Use the last user message as the query
            $lastUserMsg = collect($rawMessages)->where('role', 'user')->last();
            if ($lastUserMsg && !empty($lastUserMsg['content'])) {
                try {
                    $embeddingService = new EmbeddingService();
                    $searchService = new VectorSearchService($embeddingService);
                    $relevantChunks = $searchService->search($lastUserMsg['content'], topK: 5);

                    if ($relevantChunks->isNotEmpty()) {
                        $ragContext = $searchService->formatContext($relevantChunks);
                        $ragCitations = $searchService->getCitations($relevantChunks);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('RAG search failed (temp): ' . $e->getMessage());
                }
            }
        }

        $messagesContext = $this->buildContext($messages, $pdfTexts, $ragContext);
        $aiService = AIFactory::make($provider);

        return response()->stream(function () use ($aiService, $messagesContext, $provider, $ragCitations) {
            $usedProvider = $provider;
            try {
                foreach ($aiService->stream($messagesContext) as $chunk) {
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    if (ob_get_level() > 0) { ob_flush(); }
                    flush();
                }

                // Send metadata with RAG citations
                $meta = ['provider' => $usedProvider];
                if (!empty($ragCitations)) {
                    $meta['rag_sources'] = $ragCitations;
                }
                echo "data: " . json_encode(['meta' => $meta]) . "\n\n";
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            }
            echo "event: close\ndata: [DONE]\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Content-Type' => 'text/event-stream',
        ]);
    }
}
