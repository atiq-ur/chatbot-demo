<?php

namespace App\Http\Controllers;

use App\Jobs\IngestDocumentJob;
use App\Jobs\IngestWebPageJob;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * List all documents with status and chunk counts.
     */
    public function index(Request $request)
    {
        $query = Document::with('uploader:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->get());
    }

    /**
     * Show a single document with its chunks (for admin inspection).
     */
    public function show(string $id)
    {
        $document = Document::with(['chunks' => function ($q) {
            $q->select('id', 'document_id', 'content', 'chunk_index', 'metadata')
                ->orderBy('chunk_index');
        }, 'uploader:id,name'])->findOrFail($id);

        return response()->json($document);
    }

    /**
     * Upload a document file and queue it for ingestion.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Validate supported file types
        $allowedExtensions = ['pdf', 'md', 'markdown', 'txt', 'docx', 'doc'];
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'message' => "Unsupported file type: .{$extension}. Supported: " . implode(', ', $allowedExtensions),
            ], 422);
        }

        // Store the file
        $path = $file->store('documents', 'local');

        // Create document record
        $document = Document::create([
            'title' => $request->input('title', pathinfo($originalName, PATHINFO_FILENAME)),
            'filename' => $originalName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'source_type' => 'upload',
            'category' => $request->input('category'),
            'status' => 'processing',
            'uploaded_by' => $request->user()->id,
        ]);

        // Dispatch background ingestion job
        IngestDocumentJob::dispatch($document->id);

        return response()->json([
            'message' => 'Document uploaded and queued for processing.',
            'document' => $document,
        ], 201);
    }

    /**
     * Ingest a web page URL.
     */
    public function ingestUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url|max:2048',
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $url = $request->input('url');

        // Check if this URL has already been ingested
        $existing = Document::where('source_url', $url)->first();
        if ($existing) {
            return response()->json([
                'message' => 'This URL has already been ingested. Use re-index to refresh it.',
                'document' => $existing,
            ], 409);
        }

        // Create document record
        $document = Document::create([
            'title' => $request->input('title', $url),
            'source_url' => $url,
            'source_type' => 'url',
            'category' => $request->input('category'),
            'status' => 'processing',
            'uploaded_by' => $request->user()->id,
        ]);

        // Dispatch background ingestion job
        IngestWebPageJob::dispatch($document->id);

        return response()->json([
            'message' => 'Web page queued for scraping and indexing.',
            'document' => $document,
        ], 201);
    }

    /**
     * Re-index a document (re-chunk and re-embed).
     */
    public function reindex(string $id)
    {
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        if ($document->source_type === 'url') {
            IngestWebPageJob::dispatch($document->id);
        } else {
            IngestDocumentJob::dispatch($document->id);
        }

        return response()->json([
            'message' => 'Document queued for re-indexing.',
            'document' => $document->fresh(),
        ]);
    }

    /**
     * Delete a document and all its chunks.
     */
    public function destroy(string $id)
    {
        $document = Document::findOrFail($id);

        // Delete the stored file if it exists
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        // Chunks are cascade-deleted by the foreign key
        $document->delete();

        return response()->json([
            'message' => 'Document and all associated chunks deleted.',
        ]);
    }

    /**
     * Get available categories.
     */
    public function categories()
    {
        $categories = Document::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json($categories);
    }

    /**
     * Get RAG stats for the dashboard.
     */
    public function stats()
    {
        return response()->json([
            'total_documents' => Document::count(),
            'ready_documents' => Document::where('status', 'ready')->count(),
            'processing_documents' => Document::where('status', 'processing')->count(),
            'failed_documents' => Document::where('status', 'failed')->count(),
            'total_chunks' => \App\Models\DocumentChunk::count(),
        ]);
    }
}
