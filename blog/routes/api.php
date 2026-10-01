<?php

use App\Http\Controllers\Api\BotMediaController;
use App\Http\Controllers\Api\BotPostController;
use App\Http\Controllers\Api\PublicPostController;
use Illuminate\Support\Facades\Route;

// Consumed by the Telegram sync bot. The base URL an author gives the bot is
// this prefix, e.g. https://blog.example/api/telegram
Route::prefix('telegram')->middleware('bot.auth')->group(function () {
    Route::get('/ping', [BotPostController::class, 'ping']);
    Route::post('/posts', [BotPostController::class, 'store']);
    Route::patch('/posts/{post}', [BotPostController::class, 'update']);
    Route::post('/media', [BotMediaController::class, 'store']);
});

// Consumed by the Next.js frontend.
Route::prefix('public')->group(function () {
    Route::get('/posts', [PublicPostController::class, 'index']);
    Route::get('/posts/{slug}', [PublicPostController::class, 'show']);
});
