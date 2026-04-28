<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\TextChunker;
use App\Services\RAG\WebScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestWebPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private string $documentId
    ) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (!$document || !$document->source_url) {
            Log::error("IngestWebPageJob: Document {$this->documentId} not found or has no URL");
            return;
        }

        try {
            $document->update(['status' => 'processing']);

            // 1. Scrape the web page
            $scraper = new WebScraperService();
            $result = $scraper->scrape($document->source_url);

            $text = $result['content'];

            if (empty(trim($text))) {
                throw new \Exception("No meaningful text extracted from URL.");
            }

            // Update title if it was auto-generated
            if ($document->title === $document->source_url) {
                $document->update(['title' => $result['title']]);
            }

            Log::info("IngestWebPageJob: Scraped " . mb_strlen($text) . " chars from {$document->source_url}");

            // 2. Chunk the text
            $chunker = new TextChunker(1500, 200);
            $chunks = $chunker->chunk($text, $document->title);

            if (empty($chunks)) {
                throw new \Exception("Text chunking produced no chunks.");
            }

            // 3. Generate embeddings
            $embeddingService = new EmbeddingService();
            $chunkTexts = array_column($chunks, 'content');

            try {
                $embeddings = $embeddingService->embedBatch($chunkTexts);
            } catch (\Exception $e) {
                Log::warning("Batch embedding failed, falling back to individual: " . $e->getMessage());
                $embeddings = [];
                foreach ($chunkTexts as $chunkText) {
                    $embeddings[] = $embeddingService->embed($chunkText);
                }
            }

            // 4. Store chunks
            $document->chunks()->delete();

            foreach ($chunks as $index => $chunk) {
                $document->chunks()->create([
                    'content' => $chunk['content'],
                    'embedding' => $embeddings[$index] ?? [],
                    'chunk_index' => $index,
                    'metadata' => array_merge($chunk['metadata'], [
                        'source_url' => $document->source_url,
                    ]),
                ]);
            }

            // 5. Update document status
            $document->update([
                'status' => 'ready',
                'chunk_count' => count($chunks),
                'error_message' => null,
            ]);

            Log::info("IngestWebPageJob: Successfully ingested {$document->source_url} ({$document->chunk_count} chunks)");

        } catch (\Exception $e) {
            Log::error("IngestWebPageJob: Failed for {$document->source_url} — " . $e->getMessage());

            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
