<?php

use App\Http\Controllers\Api\BotConnectController;
use App\Http\Controllers\Api\BotMediaController;
use App\Http\Controllers\Api\BotPostController;
use App\Http\Controllers\Api\PageRequestController;
use App\Http\Controllers\Api\PublicPostController;
use App\Http\Controllers\Api\TlsAskController;
use Illuminate\Support\Facades\Route;

// Consumed by the Telegram sync bot. The base URL an author gives the bot is
// this prefix, e.g. https://blog.example/api/telegram
Route::prefix('telegram')->middleware('bot.auth')->group(function () {
    Route::get('/ping', [BotPostController::class, 'ping']);
    Route::post('/posts', [BotPostController::class, 'store']);
    Route::patch('/posts/{post}', [BotPostController::class, 'update']);
    Route::post('/posts/{post}/pin', [BotPostController::class, 'pin']);
    Route::put('/posts/{post}/channel-message', [BotPostController::class, 'channelMessage']);
    Route::put('/posts/{post}/reactions', [BotPostController::class, 'reactions']);
    Route::post('/media', [BotMediaController::class, 'store']);
    Route::put('/webhook', [BotConnectController::class, 'webhook']);
});

// Calls the bot makes for the platform rather than for one author, with
// BOT_INTERNAL_SECRET. Caddy refuses /api/internal/* from outside as well.
Route::prefix('internal')->middleware('internal.auth')->group(function () {
    Route::post('/page-requests', [PageRequestController::class, 'store']);
    Route::post('/connect', [BotConnectController::class, 'connect']);
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
    Route::get('/authors/{username}/sitemap', [PublicPostController::class, 'sitemap']);
    Route::get('/authors/{username}/topics', [PublicPostController::class, 'topics']);
    Route::get('/authors/{username}/series/{slug}', [PublicPostController::class, 'series']);
    Route::get('/authors/{username}/posts/{slug}', [PublicPostController::class, 'show']);
    // One reader is one view a minute per post at most; the site passes their address.
    Route::post('/authors/{username}/posts/{slug}/view', [PublicPostController::class, 'view'])
        ->middleware('throttle:views');
});
