<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Premium author's own address, like blog.aziza.uz, and when the blog last
 * saw its DNS point at this server. The reader site serves the author's
 * space there only while it is verified and Premium is on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('custom_domain', 253)->nullable()->unique();
            $table->timestamp('custom_domain_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['custom_domain']);
            $table->dropColumn(['custom_domain', 'custom_domain_verified_at']);
        });
    }
};
