<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Channel owners who pressed "Request a page" on the reader site. The bot
 * records them here as well as messaging the admin, so they can be worked
 * through in the admin: one row per Telegram user, however often they ask.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->string('name');
            $table->string('telegram_username')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('status', 16)->default('new')->index();
            // The author account made for them, once it exists.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('times_requested')->default(1);
            $table->timestamp('last_requested_at');
            $table->timestamps();
        });

        // Only the platform's admin sees page requests; every other account
        // is an author. The oldest account is the one made at setup, so it
        // becomes the admin.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        $first = DB::table('users')->orderBy('id')->value('id');
        if ($first !== null) {
            DB::table('users')->where('id', $first)->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
