<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Excerpts used to be strip_tags() of the body, which ran a heading straight
 * into the next paragraph. Telegram posts never had a hand-written excerpt, so
 * theirs are regenerated; excerpts written in the admin are left alone.
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
