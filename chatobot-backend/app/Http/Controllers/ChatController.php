<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        return Chat::orderBy('updated_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'nullable|string|max:255']);
        $chat = Chat::create([
            'title' => $request->input('title', 'New Chat')
        ]);
        return response()->json($chat, 201);
    }

    public function show($id)
    {
        $chat = Chat::with('messages')->findOrFail($id);
        return response()->json($chat);
    }

    public function destroy($id)
    {
        Chat::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
