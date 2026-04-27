<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->chats()->orderBy('updated_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'nullable|string|max:255']);
        $chat = $request->user()->chats()->create([
            'title' => $request->input('title', 'New Chat')
        ]);
        return response()->json($chat, 201);
    }

    public function show(Request $request, $id)
    {
        $chat = $request->user()->chats()->with('messages')->findOrFail($id);
        return response()->json($chat);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->chats()->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
