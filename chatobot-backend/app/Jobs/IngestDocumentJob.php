<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\RAG\DocumentParser;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        private string $documentId
    ) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            Log::error("IngestDocumentJob: Document {$this->documentId} not found");
            return;
        }

        try {
            $document->update(['status' => 'processing']);

            // 1. Extract text from the file
            $parser = new DocumentParser();
            $filePath = Storage::path($document->file_path);
            $text = $parser->parse($filePath, $document->mime_type);

            if (empty(trim($text))) {
                throw new \Exception("No text could be extracted from the document.");
            }

            Log::info("IngestDocumentJob: Extracted " . mb_strlen($text) . " chars from {$document->title}");

            // 2. Chunk the text
            $chunker = new TextChunker(1500, 200);
            $chunks = $chunker->chunk($text, $document->title);

            if (empty($chunks)) {
                throw new \Exception("Text chunking produced no chunks.");
            }

            Log::info("IngestDocumentJob: Created " . count($chunks) . " chunks for {$document->title}");

            // 3. Generate embeddings for each chunk
            $embeddingService = new EmbeddingService();
            $chunkTexts = array_column($chunks, 'content');

            // Try batch embedding first, fall back to individual
            try {
                $embeddings = $embeddingService->embedBatch($chunkTexts);
            } catch (\Exception $e) {
                Log::warning("Batch embedding failed, falling back to individual: " . $e->getMessage());
                $embeddings = [];
                foreach ($chunkTexts as $chunkText) {
                    $embeddings[] = $embeddingService->embed($chunkText);
                }
            }

            // 4. Store chunks with embeddings
            $document->chunks()->delete(); // Clear any previous chunks

            foreach ($chunks as $index => $chunk) {
                $document->chunks()->create([
                    'content' => $chunk['content'],
                    'embedding' => $embeddings[$index] ?? [],
                    'chunk_index' => $index,
                    'metadata' => $chunk['metadata'],
                ]);
            }

            // 5. Update document status
            $document->update([
                'status' => 'ready',
                'chunk_count' => count($chunks),
                'error_message' => null,
            ]);

            Log::info("IngestDocumentJob: Successfully ingested {$document->title} ({$document->chunk_count} chunks)");

        } catch (\Exception $e) {
            Log::error("IngestDocumentJob: Failed for {$document->title} — " . $e->getMessage());

            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
