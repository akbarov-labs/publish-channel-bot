<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPostController extends Controller
{
    /** Feed for the Next.js frontend. */
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->with('media')
            ->latest('published_at')
            ->paginate(perPage: min((int) $request->integer('per_page', 12), 50));

        return response()->json($posts);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)
            ->where('status', 'published')
            ->with('media')
            ->firstOrFail();

        return response()->json($post);
    }
}
