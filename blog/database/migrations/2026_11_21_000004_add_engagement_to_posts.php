<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What readers do with a post: views on the site (counted per day, for the
 * admin's stats), reactions in the channel, and where the post sits in the
 * channel, which comments and "open in Telegram" links need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->bigInteger('telegram_message_id')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->json('reactions')->nullable();
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->primary(['post_id', 'day']);
            $table->index('day');
        });

        // Posts from the channel carry their message id in external_id
        // ("tg:<chat id>:<message id>"), so existing ones get it right away.
        DB::table('posts')
            ->where('external_id', 'like', 'tg:%')
            ->orderBy('id')
            ->chunkById(500, function ($posts) {
                foreach ($posts as $post) {
                    if (preg_match('/^tg:-?\d+:(\d+)$/', (string) $post->external_id, $match)) {
                        DB::table('posts')->where('id', $post->id)->update(['telegram_message_id' => (int) $match[1]]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['telegram_message_id', 'views_count', 'reactions']);
        });
    }
};
