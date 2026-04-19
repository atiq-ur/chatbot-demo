<?php

namespace App\Services\AI;

class AIFactory
{
    public static function make(string $provider): AIServiceInterface
    {
        return match ($provider) {
            'claude' => new ClaudeService(),
            'gemini' => new GeminiService(),
            'ollama' => new OllamaService(),
            default => new OpenAIService(), // defaults to openai
        };
    }
}
