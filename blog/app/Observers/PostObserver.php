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

        PublishToTelegram::dispatch($post);
    }
}
