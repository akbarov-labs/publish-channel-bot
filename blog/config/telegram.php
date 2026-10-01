<?php

return [
    // Bearer token the sync bot must present on every /api/telegram/* call.
    'api_token' => env('TELEGRAM_API_TOKEN'),

    // Where this blog announces newly published posts. Handed out by the bot
    // at the end of its /connect flow.
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

    // Shared secret used to sign the webhook body with HMAC-SHA256.
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
];
