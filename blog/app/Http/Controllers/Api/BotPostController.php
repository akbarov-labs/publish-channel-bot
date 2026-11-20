<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotPostController extends Controller
{
    /** Lets the bot verify the base URL and token during /connect. */
    public function ping(Request $request): JsonResponse
    {
        $author = $request->user();

        return response()->json([
            'ok' => true,
            'site' => config('app.name'),
            'author' => $author->username,
            'url' => $author->siteUrl(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'in:telegram'],
            'external_id' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
            'post_type' => ['nullable', 'string', 'in:article,gallery,video,audio,poll'],
            'telegraph_url' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'media' => ['array'],
            'media.*.url' => ['required', 'url'],
            'media.*.type' => ['required', 'string', 'in:photo,video,document,audio'],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:300'],
            'poll.options' => ['required_with:poll', 'array', 'min:1', 'max:12'],
            'poll.options.*.text' => ['required', 'string', 'max:200'],
            'poll.options.*.votes' => ['nullable', 'integer', 'min:0'],
            'poll.total_votes' => ['nullable', 'integer', 'min:0'],
            'poll.closed' => ['nullable', 'boolean'],
            'poll.multiple' => ['nullable', 'boolean'],
            'poll.quiz' => ['nullable', 'boolean'],
        ]);

        $author = $request->user();

        // external_id is the idempotency key: Telegram can redeliver an update.
        $existing = $author->posts()->where('external_id', $data['external_id'])->first();
        if ($existing) {
            return response()->json(['id' => (string) $existing->id, 'url' => $existing->url()]);
        }

        $media = $data['media'] ?? [];

        $post = $author->posts()->create([
            'title' => $data['title'],
            'html' => $data['html'] ?? '',
            'excerpt' => Post::excerptFrom($data['html'] ?? ''),
            'post_type' => $data['post_type'] ?? Post::TYPE_ARTICLE,
            'status' => 'published',
            'source' => Post::SOURCE_TELEGRAM,
            'external_id' => $data['external_id'],
            'telegraph_url' => $data['telegraph_url'] ?? null,
            'cover_image' => collect($media)->firstWhere('type', 'photo')['url'] ?? null,
            'published_at' => $data['published_at'] ?? now(),
            'poll' => isset($data['poll']) ? [
                'question' => $data['poll']['question'],
                'options' => array_map(fn (array $option) => [
                    'text' => $option['text'],
                    'votes' => (int) ($option['votes'] ?? 0),
                ], $data['poll']['options']),
                'total_votes' => (int) ($data['poll']['total_votes'] ?? 0),
                'closed' => (bool) ($data['poll']['closed'] ?? false),
                'multiple' => (bool) ($data['poll']['multiple'] ?? false),
                'quiz' => (bool) ($data['poll']['quiz'] ?? false),
            ] : null,
            'telegram_message_id' => preg_match('/^tg:-?\d+:(\d+)$/', $data['external_id'], $match) ? (int) $match[1] : null,
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
        // A token only ever reaches its own author's posts.
        abort_unless($post->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
        ]);

        $post->update([
            'title' => $data['title'],
            'html' => $data['html'] ?? '',
            'excerpt' => Post::excerptFrom($data['html'] ?? ''),
        ]);

        return response()->json(['id' => (string) $post->id, 'url' => $post->url()]);
    }

    /** The channel pinned this post's message: pin it on the site too. */
    public function pin(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);

        $post->update(['pinned_at' => now()]);

        return response()->json(['id' => (string) $post->id, 'pinned' => true]);
    }

    /** Where the bot announced a post written in the admin, for comments and links. */
    public function channelMessage(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);

        $data = $request->validate(['message_id' => ['required', 'integer', 'min:1']]);
        $post->forceFill(['telegram_message_id' => $data['message_id']])->saveQuietly();

        return response()->json(['id' => (string) $post->id]);
    }

    /** The channel's reaction counts for this post, replacing the last ones. */
    public function reactions(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'reactions' => ['present', 'array', 'max:20'],
            'reactions.*.emoji' => ['required', 'string', 'max:16'],
            'reactions.*.count' => ['required', 'integer', 'min:0'],
        ]);

        $reactions = collect($data['reactions'])
            ->filter(fn (array $reaction) => $reaction['count'] > 0)
            ->sortByDesc('count')
            ->map(fn (array $reaction) => ['emoji' => $reaction['emoji'], 'count' => (int) $reaction['count']])
            ->values()
            ->all();

        $post->forceFill(['reactions' => $reactions])->saveQuietly();

        return response()->json(['id' => (string) $post->id, 'reactions' => $reactions]);
    }
}
