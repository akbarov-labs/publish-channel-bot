<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled posts and the pinned post.
 *
 * announce_pending marks a post published for a future time: the observer
 * cannot announce it yet, so posts:announce-due does it when the time comes.
 * Only posts that were scheduled carry the mark, so an old post that was
 * never announced (sync was off back then) is never sent out of the blue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable();
            $table->boolean('announce_pending')->default(false);
            $table->index(['user_id', 'pinned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'pinned_at']);
            $table->dropColumn(['pinned_at', 'announce_pending']);
        });
    }
};
