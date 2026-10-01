<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublishToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public Post $post) {}

    public function handle(): void
    {
        $this->post->loadMissing(['media', 'user']);

        // Each author connects their own bot session; the .env values remain
        // as the fallback for installs that predate per-author settings.
        $url = (string) ($this->post->user?->webhook_url ?: config('telegram.webhook_url'));
        $secret = (string) ($this->post->user?->webhook_secret ?: config('telegram.webhook_secret'));

        if ($url === '' || $secret === '') {
            Log::info('Telegram sync is not configured; skipping post '.$this->post->id);

            return;
        }

        // A post that came from Telegram must never be pushed back to Telegram.
        if ($this->post->cameFromTelegram()) {
            return;
        }

        $body = json_encode([
            'event' => 'post.published',
            'post' => [
                'id' => (string) $this->post->id,
                'title' => $this->post->title,
                'url' => $this->post->url(),
                'html' => $this->post->html,
                'excerpt' => $this->post->excerpt,
                'cover_image' => $this->post->cover_image,
                'post_type' => $this->post->post_type,
                'source' => $this->post->source,
                'media' => $this->post->media
                    ->map(fn ($item) => ['type' => $item->type, 'url' => $item->url])
                    ->all(),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The bot verifies this over the raw bytes, so send exactly what we signed.
        $signature = hash_hmac('sha256', $body, $secret);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Signature' => 'sha256='.$signature,
        ])->withBody($body, 'application/json')->post($url);

        $response->throw();

        $this->post->forceFill(['telegram_notified_at' => now()])->saveQuietly();
    }
}
