<?php

namespace App\Services\RAG;

use Illuminate\Support\Facades\Http;
use Exception;

class EmbeddingService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = env('OLLAMA_URL', 'http://localhost:11434');
        $this->model = env('OLLAMA_EMBED_MODEL', 'nomic-embed-text');
    }

    /**
     * Generate an embedding vector for a single text.
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        $response = Http::timeout(60)->post("{$this->baseUrl}/api/embed", [
            'model' => $this->model,
            'input' => $text,
        ]);

        if (!$response->successful()) {
            throw new Exception("Ollama Embedding Error: " . $response->body());
        }

        $data = $response->json();

        // Ollama /api/embed returns { embeddings: [[...]] }
        if (isset($data['embeddings'][0])) {
            return $data['embeddings'][0];
        }

        // Fallback for older Ollama versions using /api/embeddings
        if (isset($data['embedding'])) {
            return $data['embedding'];
        }

        throw new Exception("Unexpected embedding response format: " . json_encode(array_keys($data)));
    }

    /**
     * Generate embeddings for multiple texts.
     *
     * @param string[] $texts
     * @return float[][] Array of embedding vectors
     */
    public function embedBatch(array $texts): array
    {
        $embeddings = [];

        // Ollama /api/embed supports batch via array input
        $response = Http::timeout(120)->post("{$this->baseUrl}/api/embed", [
            'model' => $this->model,
            'input' => $texts,
        ]);

        if ($response->successful() && isset($response->json()['embeddings'])) {
            return $response->json()['embeddings'];
        }

        // Fallback: embed one by one
        foreach ($texts as $text) {
            $embeddings[] = $this->embed($text);
        }

        return $embeddings;
    }

    /**
     * Compute cosine similarity between two vectors.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }
}
