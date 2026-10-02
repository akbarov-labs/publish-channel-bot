<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Excerpts used to join blocks with a bare space, so a heading still read as
 * the start of the next paragraph. Each block is now its own sentence, so
 * Telegram posts (which never had a hand-written excerpt) are regenerated
 * again; excerpts written in the admin are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')
            ->where('source', Post::SOURCE_TELEGRAM)
            ->whereNotNull('html')
            ->orderBy('id')
            ->chunkById(200, function ($posts) {
                foreach ($posts as $post) {
                    DB::table('posts')->where('id', $post->id)->update(['excerpt' => Post::excerptFrom($post->html)]);
                }
            });
    }

    public function down(): void
    {
        // The old excerpts were a lossy derivative of the body; nothing to restore.
    }
};
