<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MessageController extends Controller
{
    public function store(Request $request, $chatId)
    {
        $request->validate([
            'content' => 'required|string',
            'provider' => 'nullable|string|in:openai,claude,gemini'
        ]);

        $chat = Chat::findOrFail($chatId);
        $userMessage = $chat->messages()->create([
            'role' => 'user',
            'content' => $request->input('content')
        ]);

        $messagesContext = $chat->messages()->orderBy('created_at', 'asc')->get();
        $provider = $request->input('provider', 'openai');

        try {
            if ($provider === 'claude') {
                $aiMessageContent = $this->sendToClaude($messagesContext);
            } elseif ($provider === 'gemini') {
                $aiMessageContent = $this->sendToGemini($messagesContext);
            } else {
                $aiMessageContent = $this->sendToOpenAI($messagesContext);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to AI', 'details' => $e->getMessage()], 500);
        }

        if (!$aiMessageContent) {
            return response()->json(['error' => 'No content returned from AI provider'], 500);
        }

        $aiMessage = $chat->messages()->create([
            'role' => 'assistant',
            'content' => $aiMessageContent
        ]);

        return response()->json($aiMessage, 201);
    }

    private function sendToOpenAI($messages)
    {
        $formattedMessages = $messages->map(function ($msg) {
            return ['role' => $msg->role, 'content' => $msg->content];
        })->toArray();

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => $formattedMessages,
            ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content');
        }
        throw new \Exception('OpenAI Error: ' . $response->body());
    }

    private function sendToClaude($messages)
    {
        // Claude does not support initial non-user messages well, we map standardly
        $formattedMessages = $messages->map(function ($msg) {
            return ['role' => $msg->role, 'content' => $msg->content];
        })->toArray();

        $response = Http::withHeaders([
            'x-api-key' => env('ANTHROPIC_API_KEY'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json'
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1024,
            'messages' => $formattedMessages,
        ]);

        if ($response->successful()) {
            return $response->json('content.0.text');
        }
        throw new \Exception('Claude Error: ' . $response->body());
    }

    private function sendToGemini($messages)
    {
        $formattedMessages = $messages->map(function ($msg) {
            // Gemini uses "user" and "model" roles instead of "assistant"
            $role = $msg->role === 'assistant' ? 'model' : 'user';
            return [
                'role' => $role,
                'parts' => [['text' => $msg->content]]
            ];
        })->toArray();

        $apiKey = env('GEMINI_API_KEY');
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent?key={$apiKey}", [
            'contents' => $formattedMessages
        ]);
        info('from gemini');
        info($response);

        if ($response->successful()) {
            return $response->json('candidates.0.content.parts.0.text');
        }
        throw new \Exception('Gemini Error: ' . $response->body());
    }
}
