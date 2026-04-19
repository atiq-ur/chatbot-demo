<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Exception;

class GeminiService implements AIServiceInterface
{
    private function formatMessages($messagesContext)
    {
        return $messagesContext->map(function ($msg) {
            $role = $msg->role === 'assistant' ? 'model' : 'user';
            $parts = [];
            
            $attachments = $msg->attachments ?? [];
            if (!empty($attachments)) {
                foreach ($attachments as $path) {
                    if (\Illuminate\Support\Facades\Storage::exists($path)) {
                        $mime = \Illuminate\Support\Facades\Storage::mimeType($path);
                        $base64 = base64_encode(\Illuminate\Support\Facades\Storage::get($path));
                        // Gemini vision format
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $mime,
                                'data' => $base64
                            ]
                        ];
                    }
                }
            }
            $parts[] = ['text' => $msg->content ?? ''];
            
            return [
                'role' => $role,
                'parts' => $parts
            ];
        })->toArray();
    }

    public function send($messagesContext): string
    {
        $formattedMessages = $this->formatMessages($messagesContext);

        $apiKey = env('GEMINI_API_KEY', env('OPENAI_API_KEY')); // Fallback for safety
        $response = Http::post("https://api.openai.com/v1/chat/completions", [
            'model' => 'gpt-3.5-turbo',
            'messages' => $formattedMessages,
        ]);
        
        if ($response->successful()) {
            return $response->json('choices.0.message.content');
        }
        throw new Exception('Gemini Error: ' . $response->body());
    }

    public function stream($messagesContext): \Generator
    {
        $formattedMessages = $this->formatMessages($messagesContext);

        $apiKey = env('GEMINI_API_KEY', env('OPENAI_API_KEY')); 
        $response = Http::withOptions(['stream' => true])
            ->post("https://api.openai.com/v1/chat/completions", [
            'model' => 'gpt-3.5-turbo',
            'messages' => $formattedMessages,
            'stream' => true,
        ]);

        if (!$response->successful()) {
            throw new Exception('Gemini Streaming Error: ' . $response->body());
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (str_starts_with($line, 'data: ') && $line !== 'data: [DONE]') {
                    $jsonStr = substr($line, 6);
                    $json = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['choices'][0]['delta']['content'])) {
                        yield $json['choices'][0]['delta']['content'];
                    }
                }
            }
        }
    }
}
