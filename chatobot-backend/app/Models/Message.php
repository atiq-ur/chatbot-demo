<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['chat_id', 'role', 'content', 'attachments', 'provider'];

    protected $casts = [
        'attachments' => 'array'
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
