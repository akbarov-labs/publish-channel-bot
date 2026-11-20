<?php

return [
    // Bearer token the sync bot must present on every /api/telegram/* call.
    'api_token' => env('TELEGRAM_API_TOKEN'),

    // Where this blog announces newly published posts. Handed out by the bot
    // at the end of its /connect flow.
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

    // Shared secret used to sign the webhook body with HMAC-SHA256.
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    // Bearer secret the bot presents on /api/internal/* (page requests). The
    // same value is set on the bot. Unset, those routes answer 404.
    'internal_secret' => env('BOT_INTERNAL_SECRET'),
];
