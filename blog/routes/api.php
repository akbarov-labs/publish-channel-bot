<?php

use App\Http\Controllers\Api\BotMediaController;
use App\Http\Controllers\Api\BotPostController;
use App\Http\Controllers\Api\PublicPostController;
use App\Http\Controllers\Api\TlsAskController;
use Illuminate\Support\Facades\Route;

// Consumed by the Telegram sync bot. The base URL an author gives the bot is
// this prefix, e.g. https://blog.example/api/telegram
Route::prefix('telegram')->middleware('bot.auth')->group(function () {
    Route::get('/ping', [BotPostController::class, 'ping']);
    Route::post('/posts', [BotPostController::class, 'store']);
    Route::patch('/posts/{post}', [BotPostController::class, 'update']);
    Route::post('/media', [BotMediaController::class, 'store']);
});

// Asked by the edge Caddy before it gets a certificate for an author's host.
// Reached over the Docker network; the public route is blocked at Caddy.
Route::get('/tls/allowed', TlsAskController::class);

// Consumed by the Next.js frontend.
Route::prefix('public')->group(function () {
    Route::get('/posts', [PublicPostController::class, 'index']);
    Route::get('/authors', [PublicPostController::class, 'authors']);
    Route::get('/authors/{username}', [PublicPostController::class, 'author']);
    Route::get('/authors/{username}/posts', [PublicPostController::class, 'authorPosts']);
    Route::get('/authors/{username}/posts/{slug}', [PublicPostController::class, 'show']);
});
