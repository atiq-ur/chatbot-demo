<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'filename',
        'file_path',
        'source_url',
        'mime_type',
        'source_type',
        'category',
        'chunk_count',
        'status',
        'error_message',
        'uploaded_by',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }
}
