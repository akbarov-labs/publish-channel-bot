<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One blog, many authors: every user is an author with their own subdomain,
 * their own bot token and their own webhook back to the bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The author's subdomain: <username>.<frontend domain>.
            $table->string('username', 32)->nullable()->unique()->after('name');
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            // Public channel link shown on the site, e.g. @mychannel.
            $table->string('telegram_channel')->nullable();

            // sha256 of the bearer token the bot presents for this author.
            $table->string('bot_token_hash', 64)->nullable()->unique();
            // Handed out by the bot at the end of /connect.
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->backfill();

        // Slugs only need to be unique inside one author's space.
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['bot_token_hash']);
            $table->dropColumn([
                'username', 'bio', 'avatar', 'telegram_channel',
                'bot_token_hash', 'webhook_url', 'webhook_secret',
            ]);
        });
    }

    /** Gives existing users a username and hands existing posts to the first one. */
    private function backfill(): void
    {
        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            $base = Str::slug($user->name) ?: Str::slug(Str::before($user->email, '@')) ?: 'author';
            $base = substr($base, 0, 28);
            $username = $base;
            $suffix = 2;

            while (in_array($username, $taken, true)) {
                $username = "{$base}-{$suffix}";
                $suffix++;
            }

            $taken[] = $username;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        $first = DB::table('users')->orderBy('id')->value('id');
        if ($first !== null) {
            DB::table('posts')->whereNull('user_id')->update(['user_id' => $first]);
        }
    }
};
