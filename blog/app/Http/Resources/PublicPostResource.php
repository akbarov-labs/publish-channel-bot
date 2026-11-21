<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post as the reader site sees it. The body is left out of listings to keep
 * feed pages light; the single-post endpoint includes it.
 *
 * @mixin Post
 */
class PublicPostResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, private bool $withBody = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'html' => $this->when($this->withBody, $this->html),
            'post_type' => $this->post_type,
            'cover_image' => User::publicUrl($this->cover_image),
            'telegraph_url' => $this->telegraph_url,
            'published_at' => $this->published_at?->toAtomString(),
            'pinned' => $this->pinned_at !== null,
            // Listings carry no body, so the read time travels with every post.
            'reading_minutes' => $this->readingMinutes(),
            'views' => (int) $this->views_count,
            'reactions' => $this->reactions ?? [],
            'poll' => $this->poll,
            // "channel/123": the post in its channel, for comments and links.
            'telegram_post' => ($channel = $this->user?->channelUsername()) && $this->telegram_message_id
                ? "{$channel}/{$this->telegram_message_id}"
                : null,
            'url' => $this->url(),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($item) => [
                'id' => $item->id,
                'url' => User::publicUrl($item->url),
                'type' => $item->type,
                'position' => $item->position,
            ])->values()),
            'topics' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values()),
            'author' => new AuthorResource($this->whenLoaded('user')),
        ];
    }

    /** Minutes at 200 words a minute, the same rule as the reader site's post page. */
    private function readingMinutes(): int
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('~<[^>]+>~', ' $0 ', (string) $this->html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);

        return max(1, (int) round($words / 200));
    }
}
