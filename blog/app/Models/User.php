<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'email', 'password', 'bio', 'avatar', 'telegram_channel', 'webhook_url', 'webhook_secret'])]
#[Hidden(['password', 'remember_token', 'bot_token_hash', 'webhook_secret'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Subdomains that must never belong to an author: they are either the
     * platform's own hosts or names a reader could mistake for it.
     */
    public const RESERVED_USERNAMES = [
        'www', 'api', 'admin', 'app', 'blog', 'mail', 'static', 'assets',
        'cdn', 'media', 'storage', 'help', 'support', 'status', 'sites',
    ];

    /** Lowercase letters, digits and inner hyphens: a valid DNS label. */
    public const USERNAME_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,30}[a-z0-9])$/';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'webhook_secret' => 'encrypted',
        ];
    }

    /**
     * Every registered user is an author of this blog, so all of them may open
     * the admin. Tighten this if the blog ever gains non-author accounts.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Creates a new bot token and returns it. Only its hash is stored, so the
     * plain value can be shown to the author exactly once.
     */
    public function issueBotToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['bot_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public static function findByBotToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return static::where('bot_token_hash', hash('sha256', $token))->first();
    }

    /** The author's space on the reader site: <username>.<frontend domain>. */
    public function siteUrl(): string
    {
        $frontend = (string) config('app.frontend_url', config('app.url'));
        $parts = parse_url($frontend) ?: [];

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$this->username}.{$host}{$port}";
    }

    public function avatarUrl(): ?string
    {
        return static::publicUrl($this->avatar);
    }

    /** Admin uploads are stored as disk paths; anything else is already a URL. */
    public static function publicUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    /** A t.me link for the channel, whether it was saved as @name, name or a URL. */
    public function telegramUrl(): ?string
    {
        $channel = trim((string) $this->telegram_channel);

        if ($channel === '') {
            return null;
        }

        if (Str::startsWith($channel, ['http://', 'https://'])) {
            return $channel;
        }

        return 'https://t.me/'.ltrim($channel, '@');
    }
}
