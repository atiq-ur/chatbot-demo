<?php

namespace App\Services\RAG;

use Illuminate\Support\Facades\Http;
use Exception;

class WebScraperService
{
    /**
     * Scrape text content from a URL.
     * Extracts meaningful content, strips scripts/styles/nav elements.
     */
    public function scrape(string $url): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'NexusAI-RAG-Bot/1.0 (internal knowledge scraper)',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch URL: {$url} — HTTP {$response->status()}");
        }

        $html = $response->body();
        $title = $this->extractTitle($html);
        $text = $this->htmlToText($html);

        if (empty(trim($text))) {
            throw new Exception("No meaningful text content extracted from: {$url}");
        }

        return [
            'title' => $title ?: parse_url($url, PHP_URL_HOST),
            'content' => $text,
        ];
    }

    /**
     * Extract the page title.
     */
    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $match)) {
            return html_entity_decode(trim($match[1]), ENT_QUOTES, 'UTF-8');
        }
        return null;
    }

    /**
     * Convert HTML to clean text, removing scripts, styles, and navigation.
     */
    private function htmlToText(string $html): string
    {
        // Remove script and style tags first
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Try to extract main content areas first
        $mainContent = '';
        if (preg_match('/<main[^>]*>(.*?)<\/main>/si', $html, $match)) {
            $mainContent = $match[1];
        } elseif (preg_match('/<article[^>]*>(.*?)<\/article>/si', $html, $match)) {
            $mainContent = $match[1];
        } elseif (preg_match('/<div[^>]*(?:class|id)=["\'][^"\']*(?:content|main|article)[^"\']*["\'][^>]*>(.*?)<\/div>/si', $html, $match)) {
            $mainContent = $match[1];
        }

        $text = !empty($mainContent) ? $mainContent : $html;

        // Convert common block elements to newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/?(p|div|li|h[1-6]|tr|blockquote|pre)[^>]*>/i', "\n", $text);

        // Convert headings to markdown-style for better chunking
        $text = preg_replace_callback('/<h([1-6])[^>]*>(.*?)<\/h\1>/si', function ($m) {
            $level = $m[1];
            $heading = strip_tags($m[2]);
            return "\n" . str_repeat('#', (int) $level) . ' ' . trim($heading) . "\n";
        }, $text);

        // Convert links to include URL
        $text = preg_replace_callback('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si', function ($m) {
            $linkText = strip_tags($m[2]);
            return $linkText;
        }, $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/^\s+$/m', '', $text);

        return trim($text);
    }
}
