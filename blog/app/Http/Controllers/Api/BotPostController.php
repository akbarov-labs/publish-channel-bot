<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BotPostController extends Controller
{
    /** Lets the bot verify the base URL and token during /connect. */
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'site' => config('app.name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'in:telegram'],
            'external_id' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
            'post_type' => ['nullable', 'string', 'in:article,gallery,video'],
            'telegraph_url' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'media' => ['array'],
            'media.*.url' => ['required', 'url'],
            'media.*.type' => ['required', 'string', 'in:photo,video,document'],
        ]);

        // external_id is the idempotency key: Telegram can redeliver an update.
        $existing = Post::where('external_id', $data['external_id'])->first();
        if ($existing) {
            return response()->json(['id' => (string) $existing->id, 'url' => $existing->url()]);
        }

        $media = $data['media'] ?? [];

        $post = Post::create([
            'title' => $data['title'],
            'html' => $data['html'] ?? '',
            'excerpt' => Str::limit(strip_tags((string) ($data['html'] ?? '')), 280),
            'post_type' => $data['post_type'] ?? Post::TYPE_ARTICLE,
            'status' => 'published',
            'source' => Post::SOURCE_TELEGRAM,
            'external_id' => $data['external_id'],
            'telegraph_url' => $data['telegraph_url'] ?? null,
            'cover_image' => collect($media)->firstWhere('type', 'photo')['url'] ?? null,
            'published_at' => $data['published_at'] ?? now(),
        ]);

        foreach (array_values($media) as $position => $item) {
            $post->media()->create([
                'url' => $item['url'],
                'type' => $item['type'],
                'position' => $position,
            ]);
        }

        return response()->json(['id' => (string) $post->id, 'url' => $post->url()], 201);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
        ]);

        $post->update([
            'title' => $data['title'],
            'html' => $data['html'] ?? '',
            'excerpt' => Str::limit(strip_tags((string) ($data['html'] ?? '')), 280),
        ]);

        return response()->json(['id' => (string) $post->id, 'url' => $post->url()]);
    }
}
