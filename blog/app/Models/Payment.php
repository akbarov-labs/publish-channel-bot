<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period of Premium: paid in Telegram Stars, or granted by the admin.
 * Written by the bot's payment calls and the admin's Grant Premium, never by
 * a form, so nothing here is fillable from a request.
 */
class Payment extends Model
{
    public const PROVIDER_STARS = 'stars';
    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDERS = [
        self::PROVIDER_STARS => 'Telegram Stars',
        self::PROVIDER_MANUAL => 'Granted by the admin',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'telegram_user_id' => 'integer',
            'is_recurring' => 'boolean',
            'premium_until' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
