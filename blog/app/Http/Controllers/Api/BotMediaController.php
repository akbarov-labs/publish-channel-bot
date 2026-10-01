<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotMediaController extends Controller
{
    /** Receives a file downloaded from Telegram and returns its public URL. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Telegram's own Bot API download cap is 20 MB.
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $path = $request->file('file')->store('posts', 'public');

        return response()->json(['url' => asset('storage/'.$path)], 201);
    }
}
