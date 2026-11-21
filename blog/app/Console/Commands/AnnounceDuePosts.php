<?php

namespace App\Console\Commands;

use App\Jobs\PublishToTelegram;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Announces scheduled posts in the channel once their time has come. Runs
 * every minute (routes/console.php); the reader site shows them on its own,
 * since it only lists posts whose time has passed.
 */
#[Signature('posts:announce-due')]
#[Description('Announce scheduled posts whose publication time has come')]
class AnnounceDuePosts extends Command
{
    public function handle(): int
    {
        $due = Post::query()
            ->where('announce_pending', true)
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            // A blocked author's posts wait; they go out if the author is unblocked.
            ->whereHas('user', fn ($author) => $author->active())
            ->get();

        foreach ($due as $post) {
            $post->forceFill(['announce_pending' => false])->saveQuietly();

            if ($post->telegram_notified_at === null && ! $post->cameFromTelegram()) {
                PublishToTelegram::dispatch($post);
            }
        }

        if ($due->isNotEmpty()) {
            $this->info("Announced {$due->count()} scheduled post(s).");
        }

        return self::SUCCESS;
    }
}
