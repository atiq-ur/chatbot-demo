<?php

namespace App\Services\RAG;

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Exception;

class DocumentParser
{
    /**
     * Extract text from a file based on its MIME type or extension.
     */
    public function parse(string $filePath, string $mimeType): string
    {
        return match (true) {
            $this->isPdf($mimeType, $filePath) => $this->parsePdf($filePath),
            $this->isDocx($mimeType, $filePath) => $this->parseDocx($filePath),
            $this->isMarkdown($mimeType, $filePath) => $this->parseText($filePath),
            $this->isPlainText($mimeType, $filePath) => $this->parseText($filePath),
            default => throw new Exception("Unsupported file type: {$mimeType}"),
        };
    }

    /**
     * Extract text from a PDF file using smalot/pdfparser.
     */
    private function parsePdf(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        $text = '';

        foreach ($pdf->getPages() as $pageIndex => $page) {
            $pageText = $page->getText();
            if (!empty(trim($pageText))) {
                $text .= "--- Page " . ($pageIndex + 1) . " ---\n";
                $text .= $pageText . "\n\n";
            }
        }

        return $this->cleanText($text);
    }

    /**
     * Extract text from a DOCX file using phpoffice/phpword.
     */
    private function parseDocx(string $filePath): string
    {
        $phpWord = WordIOFactory::load($filePath, 'Word2007');
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractElementText($element) . "\n";
            }
        }

        return $this->cleanText($text);
    }

    /**
     * Recursively extract text from PHPWord elements.
     */
    private function extractElementText($element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $result = $element->getText();
            if (is_string($result)) {
                $text .= $result;
            } elseif (is_object($result) && method_exists($result, 'getText')) {
                $text .= $result->getText();
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractElementText($child);
            }
        }

        return $text;
    }

    /**
     * Read plain text or markdown files.
     */
    private function parseText(string $filePath): string
    {
        return $this->cleanText(file_get_contents($filePath));
    }

    /**
     * Clean extracted text.
     */
    private function cleanText(string $text): string
    {
        // Remove null bytes and control characters (keep newlines and tabs)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    // --- Type detection helpers ---

    private function isPdf(string $mimeType, string $path): bool
    {
        return $mimeType === 'application/pdf' || str_ends_with(strtolower($path), '.pdf');
    }

    private function isDocx(string $mimeType, string $path): bool
    {
        return str_contains($mimeType, 'wordprocessingml')
            || str_contains($mimeType, 'msword')
            || str_ends_with(strtolower($path), '.docx')
            || str_ends_with(strtolower($path), '.doc');
    }

    private function isMarkdown(string $mimeType, string $path): bool
    {
        return str_ends_with(strtolower($path), '.md')
            || str_ends_with(strtolower($path), '.markdown');
    }

    private function isPlainText(string $mimeType, string $path): bool
    {
        return str_starts_with($mimeType, 'text/')
            || str_ends_with(strtolower($path), '.txt');
    }
}
