<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class OllamaService implements AIServiceInterface
{
    private function formatMessages($messagesContext)
    {
        return $messagesContext->map(function ($msg) {
            $message = ['role' => $msg->role, 'content' => $msg->content];
            $attachments = $msg->attachments ?? [];
            if (!empty($attachments)) {
                $images = [];
                foreach ($attachments as $att) {
                    $path = is_array($att) ? $att['path'] : $att;
                    $type = is_array($att) ? ($att['type'] ?? 'image') : 'image';
                    if ($type === 'image' && Storage::exists($path)) {
                        $images[] = base64_encode(Storage::get($path));
                    }
                }
                if (!empty($images)) $message['images'] = $images;
            }
            return $message;
        })->toArray();
    }

    public function send($messagesContext): string
    {
        $response = Http::post(env('OLLAMA_URL', 'http://localhost:11434') . '/api/chat', [
            'model' => env('OLLAMA_MODEL', 'deepseek-v3.2:cloud'),
            'messages' => $this->formatMessages($messagesContext),
            'stream' => false,
        ]);
        if ($response->successful()) return $response->json('message.content');
        throw new Exception('Ollama Error: ' . $response->body());
    }

    public function stream($messagesContext): \Generator
    {
        $response = Http::withOptions(['stream' => true])
            ->timeout(120)
            ->post(env('OLLAMA_URL', 'http://localhost:11434') . '/api/chat', [
                'model' => env('OLLAMA_MODEL', 'deepseek-v3.2:cloud'),
                'messages' => $this->formatMessages($messagesContext),
                'stream' => true,
            ]);
        if (!$response->successful()) throw new Exception('Ollama Streaming Error: ' . $response->body());
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line !== '') {
                    $json = json_decode($line, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['message']['content'])) {
                        yield $json['message']['content'];
                    }
                }
            }
        }
    }
}
