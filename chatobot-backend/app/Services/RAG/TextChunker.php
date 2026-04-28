<?php

namespace App\Services\RAG;

class TextChunker
{
    private int $maxChars;
    private int $overlap;

    public function __construct(int $maxChars = 1500, int $overlap = 200)
    {
        $this->maxChars = $maxChars;
        $this->overlap = $overlap;
    }

    /**
     * Split text into overlapping chunks, respecting paragraph and heading boundaries.
     *
     * @return array<int, array{content: string, metadata: array}>
     */
    public function chunk(string $text, ?string $sourceTitle = null): array
    {
        $text = $this->normalizeWhitespace($text);

        if (empty(trim($text))) {
            return [];
        }

        // Split on double newlines (paragraph boundaries) or markdown headings
        $paragraphs = preg_split('/\n{2,}/', $text);
        $paragraphs = array_filter($paragraphs, fn($p) => !empty(trim($p)));
        $paragraphs = array_values($paragraphs);

        $chunks = [];
        $currentChunk = '';
        $currentSection = $sourceTitle ?? 'Document';
        $chunkIndex = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            // Detect markdown headings for section metadata
            if (preg_match('/^#{1,6}\s+(.+)$/m', $paragraph, $match)) {
                $currentSection = trim($match[1]);
            }

            // If adding this paragraph would exceed the limit, finalize the current chunk
            if (!empty($currentChunk) && (mb_strlen($currentChunk) + mb_strlen($paragraph) + 2) > $this->maxChars) {
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'metadata' => [
                        'section' => $currentSection,
                        'chunk_index' => $chunkIndex,
                    ],
                ];
                $chunkIndex++;

                // Start the new chunk with overlap from the end of the previous chunk
                $currentChunk = $this->getOverlapText($currentChunk) . "\n\n" . $paragraph;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : "\n\n") . $paragraph;
            }
        }

        // Don't forget the last chunk
        if (!empty(trim($currentChunk))) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'metadata' => [
                    'section' => $currentSection,
                    'chunk_index' => $chunkIndex,
                ],
            ];
        }

        // If any single chunk is still too large, split it further by sentences
        $finalChunks = [];
        $finalIndex = 0;

        foreach ($chunks as $chunk) {
            if (mb_strlen($chunk['content']) > $this->maxChars * 1.5) {
                $subChunks = $this->splitBySentence($chunk['content'], $chunk['metadata']['section']);
                foreach ($subChunks as $sub) {
                    $sub['metadata']['chunk_index'] = $finalIndex;
                    $finalChunks[] = $sub;
                    $finalIndex++;
                }
            } else {
                $chunk['metadata']['chunk_index'] = $finalIndex;
                $finalChunks[] = $chunk;
                $finalIndex++;
            }
        }

        return $finalChunks;
    }

    /**
     * Get the last N characters for overlap context.
     */
    private function getOverlapText(string $text): string
    {
        if (mb_strlen($text) <= $this->overlap) {
            return $text;
        }

        $tail = mb_substr($text, -$this->overlap);

        // Try to start at a sentence or word boundary
        $sentencePos = preg_match('/[.!?]\s/', $tail, $match, PREG_OFFSET_CAPTURE);
        if ($sentencePos && $match[0][1] < mb_strlen($tail) / 2) {
            return trim(mb_substr($tail, $match[0][1] + mb_strlen($match[0][0])));
        }

        // Fallback: start at a word boundary
        $spacePos = mb_strpos($tail, ' ');
        if ($spacePos !== false) {
            return trim(mb_substr($tail, $spacePos));
        }

        return trim($tail);
    }

    /**
     * Split large text by sentence boundaries.
     */
    private function splitBySentence(string $text, string $section): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            if (!empty($currentChunk) && (mb_strlen($currentChunk) + mb_strlen($sentence) + 1) > $this->maxChars) {
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'metadata' => ['section' => $section, 'chunk_index' => 0],
                ];
                $currentChunk = $this->getOverlapText($currentChunk) . ' ' . $sentence;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
            }
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'metadata' => ['section' => $section, 'chunk_index' => 0],
            ];
        }

        return $chunks;
    }

    /**
     * Normalize whitespace and remove control characters.
     */
    private function normalizeWhitespace(string $text): string
    {
        // Replace tabs with spaces
        $text = str_replace("\t", '    ', $text);
        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        // Remove excess blank lines (more than 2 newlines → 2)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return $text;
    }
}
