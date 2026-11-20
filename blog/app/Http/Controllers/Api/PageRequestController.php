<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The bot reports each "Request a page" press here, so the admin can work
 * through them under Page requests.
 */
class PageRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            // Telegram usernames: letters, digits and underscores; 5-32 long,
            // or 4 for the short ones sold on Fragment.
            'telegram_username' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_]{4,32}$/'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $pageRequest = PageRequest::record($data);

        return response()->json([
            'id' => $pageRequest->id,
            'status' => $pageRequest->status,
            'times_requested' => $pageRequest->times_requested,
        ], $pageRequest->wasRecentlyCreated ? 201 : 200);
    }
}
