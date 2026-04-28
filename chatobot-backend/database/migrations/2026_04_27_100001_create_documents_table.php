<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('filename')->nullable();       // null for URL sources
            $table->string('file_path')->nullable();       // null for URL sources
            $table->string('source_url')->nullable();      // for web-scraped docs
            $table->string('mime_type')->nullable();
            $table->enum('source_type', ['upload', 'url'])->default('upload');
            $table->string('category')->nullable();
            $table->integer('chunk_count')->default(0);
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
