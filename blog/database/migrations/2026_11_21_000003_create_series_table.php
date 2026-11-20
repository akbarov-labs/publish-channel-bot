<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Series: posts an author groups into numbered parts ("Part 2 of 5"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            // Order within the series; posts without one follow by date.
            $table->unsignedInteger('series_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('series_position');
        });
        Schema::dropIfExists('series');
    }
};
