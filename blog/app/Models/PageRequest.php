<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A channel owner who pressed "Request a page" on the reader site, as the bot
 * saw them in Telegram. One row per Telegram user: asking again bumps the
 * count instead of adding a row.
 */
class PageRequest extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_ACCEPTED => 'Author created',
        self::STATUS_DISMISSED => 'Dismissed',
    ];

    protected $fillable = [
        'telegram_user_id', 'name', 'telegram_username', 'language', 'status',
        'user_id', 'times_requested', 'last_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'last_requested_at' => 'datetime',
        ];
    }

    /**
     * Records a press of "Request a page". Someone who asks again after being
     * dismissed is back in the queue; someone who already has an account keeps
     * it, and only their details are refreshed.
     *
     * @param  array{telegram_user_id: int, name: string, telegram_username?: ?string, language?: ?string}  $data
     */
    public static function record(array $data): self
    {
        $request = static::firstOrNew(['telegram_user_id' => $data['telegram_user_id']]);
        $isNew = ! $request->exists;

        $request->fill([
            'name' => $data['name'],
            'telegram_username' => $data['telegram_username'] ?? null,
            'language' => $data['language'] ?? null,
            'last_requested_at' => now(),
        ]);

        if ($isNew) {
            $request->status = self::STATUS_NEW;
            $request->times_requested = 1;
        } else {
            $request->times_requested++;
            if ($request->status === self::STATUS_DISMISSED) {
                $request->status = self::STATUS_NEW;
            }
        }

        $request->save();

        return $request;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A link that opens a Telegram chat with them. */
    public function chatUrl(): string
    {
        return $this->telegram_username
            ? 'https://t.me/'.$this->telegram_username
            : 'tg://user?id='.$this->telegram_user_id;
    }

    /**
     * A subdomain to suggest when making their account: their Telegram
     * username, if it is a valid one that nobody has yet.
     */
    public function suggestedUsername(): ?string
    {
        $candidate = str_replace('_', '-', strtolower((string) $this->telegram_username));

        if (
            ! preg_match(User::USERNAME_PATTERN, $candidate)
            || in_array($candidate, User::RESERVED_USERNAMES, true)
            || User::where('username', $candidate)->exists()
        ) {
            return null;
        }

        return $candidate;
    }
}
