<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class ClaudeService implements AIServiceInterface
{
    private function formatMessages($messagesContext)
    {
        // Claude doesn't support system role in messages array — skip it (passed via system param)
        return $messagesContext->filter(fn($m) => $m->role !== 'system')->map(function ($msg) {
            $attachments = $msg->attachments ?? [];
            if (!empty($attachments)) {
                $content = [];
                foreach ($attachments as $att) {
                    $path = is_array($att) ? $att['path'] : $att;
                    $type = is_array($att) ? ($att['type'] ?? 'image') : 'image';
                    if ($type === 'image' && Storage::exists($path)) {
                        $mime = Storage::mimeType($path);
                        $base64 = base64_encode(Storage::get($path));
                        $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]];
                    }
                }
                $content[] = ['type' => 'text', 'text' => $msg->content];
                return ['role' => $msg->role, 'content' => $content];
            }
            return ['role' => $msg->role, 'content' => $msg->content];
        })->values()->toArray();
    }

    private function getSystemPrompt($messagesContext): ?string
    {
        $system = $messagesContext->first(fn($m) => $m->role === 'system');
        return $system ? $system->content : null;
    }

    public function send($messagesContext): string
    {
        $payload = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4096,
            'messages' => $this->formatMessages($messagesContext),
        ];
        if ($sys = $this->getSystemPrompt($messagesContext)) $payload['system'] = $sys;

        $response = Http::withHeaders(['x-api-key' => env('ANTHROPIC_API_KEY'), 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', $payload);
        if ($response->successful()) return $response->json('content.0.text');
        throw new Exception('Claude Error: ' . $response->body());
    }

    public function stream($messagesContext): \Generator
    {
        $payload = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4096,
            'messages' => $this->formatMessages($messagesContext),
            'stream' => true,
        ];
        if ($sys = $this->getSystemPrompt($messagesContext)) $payload['system'] = $sys;

        $response = Http::withHeaders(['x-api-key' => env('ANTHROPIC_API_KEY'), 'anthropic-version' => '2023-06-01'])
            ->withOptions(['stream' => true])
            ->post('https://api.anthropic.com/v1/messages', $payload);
        if (!$response->successful()) throw new Exception('Claude Streaming Error: ' . $response->body());
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if (str_starts_with($line, 'data: ')) {
                    $json = json_decode(substr($line, 6), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['delta']['text'])) {
                        yield $json['delta']['text'];
                    }
                }
            }
        }
    }
}
