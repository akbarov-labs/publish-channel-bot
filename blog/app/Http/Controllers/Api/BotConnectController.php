<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets the bot finish /connect without the author copying anything between
 * the admin and Telegram: no API address, no webhook URL or secret.
 */
class BotConnectController extends Controller
{
    /**
     * Internal (BOT_INTERNAL_SECRET). An author made from a page request is
     * known by their Telegram id, so the bot can connect them outright: they
     * get a fresh bot token and the bot's webhook in one step.
     *
     * Anyone else gets 404 with where to fetch a token by hand.
     */
    public function connect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
            'webhook_url' => ['required', 'url', 'max:255'],
            'webhook_secret' => ['required', 'string', 'min:16', 'max:255'],
        ]);

        $author = PageRequest::authorFor($data['telegram_user_id']);

        $where = [
            'api_base' => url('/api/telegram'),
            'admin_url' => url('/admin/profile'),
        ];

        if ($author === null) {
            return response()->json(['message' => 'No author has this Telegram account.'] + $where, 404);
        }

        $token = $author->issueBotToken();
        $author->forceFill([
            'webhook_url' => $data['webhook_url'],
            'webhook_secret' => $data['webhook_secret'],
        ])->save();

        return response()->json([
            'token' => $token,
            'author' => $author->username,
            'url' => $author->siteUrl(),
        ] + $where);
    }

    /**
     * As the author (bot token). Where the bot wants to hear about posts
     * published in the admin, so the author never pastes it into Profile.
     */
    public function webhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:255'],
            'secret' => ['required', 'string', 'min:16', 'max:255'],
        ]);

        $request->user()->forceFill([
            'webhook_url' => $data['url'],
            'webhook_secret' => $data['secret'],
        ])->save();

        return response()->json(['ok' => true]);
    }
}
