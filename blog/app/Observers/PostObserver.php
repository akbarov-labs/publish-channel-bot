<?php

namespace App\Observers;

use App\Jobs\PublishToTelegram;
use App\Models\Post;

class PostObserver
{
    public function saved(Post $post): void
    {
        if (! $post->isPublished() || $post->published_at === null) {
            return;
        }

        // Already announced, or it originated in the channel in the first place.
        if ($post->telegram_notified_at !== null || $post->cameFromTelegram()) {
            return;
        }

        // Scheduled for later: posts:announce-due sends it when the time comes.
        if ($post->published_at->isFuture()) {
            if (! $post->announce_pending) {
                $post->forceFill(['announce_pending' => true])->saveQuietly();
            }

            return;
        }

        PublishToTelegram::dispatch($post);
    }
}
