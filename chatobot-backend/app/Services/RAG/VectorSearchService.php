<?php

namespace App\Services\RAG;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;

class VectorSearchService
{
    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Search for the most relevant document chunks matching a query.
     *
     * @param string $query The user's question
     * @param int $topK Number of results to return
     * @param string|null $category Optional category filter
     * @param float $minScore Minimum similarity score (0-1)
     * @return Collection<DocumentChunk> Ranked chunks with similarity scores
     */
    public function search(string $query, int $topK = 5, ?string $category = null, float $minScore = 0.3): Collection
    {
        // 1. Embed the query
        $queryEmbedding = $this->embeddingService->embed($query);

        // 2. Load all chunk embeddings from ready documents
        $chunksQuery = DocumentChunk::with('document')
            ->whereHas('document', function ($q) use ($category) {
                $q->where('status', 'ready');
                if ($category) {
                    $q->where('category', $category);
                }
            });

        $chunks = $chunksQuery->get();

        if ($chunks->isEmpty()) {
            return collect();
        }

        // 3. Compute cosine similarity for each chunk
        $scored = $chunks->map(function ($chunk) use ($queryEmbedding) {
            $embedding = $chunk->embedding;

            if (empty($embedding) || !is_array($embedding)) {
                return null;
            }

            $score = EmbeddingService::cosineSimilarity($queryEmbedding, $embedding);

            $chunk->similarity_score = $score;
            return $chunk;
        })->filter()->filter(function ($chunk) use ($minScore) {
            return $chunk->similarity_score >= $minScore;
        });

        // 4. Sort by similarity (highest first) and take top K
        return $scored->sortByDesc('similarity_score')
            ->take($topK)
            ->values();
    }

    /**
     * Format retrieved chunks into a context string for the LLM system prompt.
     */
    public function formatContext(Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            return '';
        }

        $context = "=== RELEVANT COMPANY DOCUMENTS ===\n\n";

        foreach ($chunks as $chunk) {
            $docTitle = $chunk->document->title ?? 'Unknown Document';
            $section = $chunk->metadata['section'] ?? '';
            $score = round($chunk->similarity_score * 100, 1);

            $context .= "--- Source: {$docTitle}";
            if ($section) {
                $context .= " | Section: {$section}";
            }
            $context .= " (Relevance: {$score}%) ---\n";
            $context .= $chunk->content . "\n";
            $context .= "--- End Source ---\n\n";
        }

        $context .= "=== END DOCUMENTS ===";

        return $context;
    }

    /**
     * Get source citations from retrieved chunks (for frontend display).
     */
    public function getCitations(Collection $chunks): array
    {
        return $chunks->map(function ($chunk) {
            return [
                'document_id' => $chunk->document_id,
                'document_title' => $chunk->document->title ?? 'Unknown',
                'section' => $chunk->metadata['section'] ?? null,
                'relevance' => round($chunk->similarity_score * 100, 1),
                'snippet' => mb_substr($chunk->content, 0, 200) . '...',
            ];
        })->unique('document_id')->values()->toArray();
    }
}
