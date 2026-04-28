<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MessageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::post('/chats/temporary/messages', [MessageController::class, 'storeTemporary']);
    Route::get('/chats/{id}', [ChatController::class, 'show']);
    Route::delete('/chats/{id}', [ChatController::class, 'destroy']);

    Route::post('/chats/{id}/messages', [MessageController::class, 'store']);

    // Document routes — viewable by all authenticated users
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/categories', [DocumentController::class, 'categories']);
    Route::get('/documents/stats', [DocumentController::class, 'stats']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);

    // Document management — admin only
    Route::middleware('admin')->group(function () {
        Route::post('/documents/upload', [DocumentController::class, 'upload']);
        Route::post('/documents/ingest-url', [DocumentController::class, 'ingestUrl']);
        Route::post('/documents/{id}/reindex', [DocumentController::class, 'reindex']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    });
});
