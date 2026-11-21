<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every period of Premium an author got: paid in Telegram Stars, or granted
 * by the admin. A paid row keeps Telegram's charge id, which is unique per
 * payment (so a message delivered twice is counted once) and is what a refund
 * or cancelling the subscription needs later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_charge_id')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 8)->nullable();
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->timestamp('premium_until');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_charge_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
