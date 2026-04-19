<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class OpenAIService implements AIServiceInterface
{
    private function formatMessages($messagesContext, &$hasImages)
    {
        return $messagesContext->map(function ($msg) use (&$hasImages) {
            if ($msg->role === 'system') {
                return ['role' => 'system', 'content' => $msg->content];
            }
            $attachments = $msg->attachments ?? [];
            if (!empty($attachments)) {
                $content = [['type' => 'text', 'text' => $msg->content]];
                foreach ($attachments as $att) {
                    $path = is_array($att) ? $att['path'] : $att;
                    $type = is_array($att) ? ($att['type'] ?? 'image') : 'image';
                    if ($type === 'image' && Storage::exists($path)) {
                        $hasImages = true;
                        $mime = Storage::mimeType($path);
                        $base64 = base64_encode(Storage::get($path));
                        $content[] = [
                            'type' => 'image_url',
                            'image_url' => ['url' => "data:{$mime};base64,{$base64}"]
                        ];
                    }
                }
                return ['role' => $msg->role, 'content' => $content];
            }
            return ['role' => $msg->role, 'content' => $msg->content];
        })->toArray();
    }

    public function send($messagesContext): string
    {
        $hasImages = false;
        $formattedMessages = $this->formatMessages($messagesContext, $hasImages);
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $hasImages ? 'gpt-4o-mini' : 'gpt-3.5-turbo',
                'messages' => $formattedMessages,
            ]);
        if ($response->successful()) return $response->json('choices.0.message.content');
        throw new Exception('OpenAI Error: ' . $response->body());
    }

    public function stream($messagesContext): \Generator
    {
        $hasImages = false;
        $formattedMessages = $this->formatMessages($messagesContext, $hasImages);
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $hasImages ? 'gpt-4o-mini' : 'gpt-3.5-turbo',
                'messages' => $formattedMessages,
                'stream' => true,
            ]);
        if (!$response->successful()) throw new Exception('OpenAI Streaming Error: ' . $response->body());
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if (str_starts_with($line, 'data: ') && $line !== 'data: [DONE]') {
                    $json = json_decode(substr($line, 6), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['choices'][0]['delta']['content'])) {
                        yield $json['choices'][0]['delta']['content'];
                    }
                }
            }
        }
    }
}
