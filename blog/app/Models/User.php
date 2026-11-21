<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'email', 'password', 'bio', 'avatar', 'telegram_channel', 'webhook_url', 'webhook_secret', 'locale', 'template'])]
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
        // The bot's webhooks are served at bot.<domain>.
        'bot',
    ];

    /** Interface languages of the reader site, as shown in the admin. */
    public const LOCALES = [
        'uz' => "O'zbekcha",
        'ru' => 'Русский',
        'en' => 'English',
    ];

    /**
     * Designs of the reader site an author can pick, with what each is for.
     * The reader site styles each (web/src/lib/templates.ts).
     */
    public const TEMPLATES = [
        'classic' => ['Classic', 'Clean and calm: posts in a list with a small picture each, the newest given room at the top.'],
        'magazine' => ['Magazine', 'Pictures first: posts as cards in a grid, for channels with photos and covers.'],
        'notebook' => ['Notebook', 'Words first: serif type on warm paper, dates in the margin, no pictures in lists.'],
        'midnight' => ['Midnight', 'Dark at any hour: light type on deep night blue with a gold accent, posts in a list.'],
        'gazette' => ['Gazette', 'A front page: bold serif headlines on newsprint, posts as cards.'],
    ];

    /**
     * Designs that come with Premium. Without it the reader site shows
     * Classic, but the author's pick is kept for when Premium comes back.
     */
    public const PREMIUM_TEMPLATES = ['midnight', 'gazette'];

    /** The database default too, so a model that was never reloaded has it. */
    protected $attributes = [
        'locale' => 'uz',
        'template' => 'classic',
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
            'is_admin' => 'boolean',
            'blocked_at' => 'datetime',
            'premium_until' => 'datetime',
        ];
    }

    /**
     * The platform's admin, who handles page requests. Not fillable: it is set
     * once by the migration (for the oldest account) or by hand, never by a form.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Set by the admin from Authors, never by a form: a blocked author cannot
     * sign in, their bot is refused and their space is off the reader site.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function block(): void
    {
        $this->forceFill(['blocked_at' => now(), 'remember_token' => null])->save();
    }

    public function unblock(): void
    {
        $this->forceFill(['blocked_at' => null])->save();
    }

    /**
     * Days Premium stays on after premium_until. A Stars subscription renews
     * at the very end of its period, and the renewal reaches us through the
     * bot, so a late message or a bot that is down for a while must not
     * switch an author's features off.
     */
    public const PREMIUM_GRACE_DAYS = 3;

    public function isPremium(): bool
    {
        return $this->premium_until !== null
            && $this->premium_until->copy()->addDays(self::PREMIUM_GRACE_DAYS)->isFuture();
    }

    /** Authors whose Premium is on, grace days included. */
    public function scopePremium(Builder $query): void
    {
        $query->where('premium_until', '>', now()->subDays(self::PREMIUM_GRACE_DAYS));
    }

    /**
     * Moves premium_until to $until, never back: a renewal that arrives after
     * the admin gave a longer gift must not cut the gift short.
     */
    public function extendPremiumTo(CarbonInterface $until): void
    {
        if ($this->premium_until === null || $until->greaterThan($this->premium_until)) {
            $this->forceFill(['premium_until' => $until])->save();
        }
    }

    /**
     * The admin's gift or trial: $days more, counted from the end of the
     * Premium they have, or from now if they have none.
     */
    public function grantPremium(int $days, ?User $by = null, ?string $note = null): Payment
    {
        $from = $this->premium_until !== null && $this->premium_until->isFuture() ? $this->premium_until : now();
        $until = $from->copy()->addDays($days);

        $this->forceFill(['premium_until' => $until])->save();

        return $this->payments()->create([
            'provider' => Payment::PROVIDER_MANUAL,
            'premium_until' => $until,
            'granted_by' => $by?->id,
            'note' => $note,
        ]);
    }

    /** The design readers see: the author's pick, or Classic for a Premium one without Premium. */
    public function siteTemplate(): string
    {
        $template = $this->template ?? 'classic';

        if (! array_key_exists($template, self::TEMPLATES)) {
            return 'classic';
        }

        return in_array($template, self::PREMIUM_TEMPLATES, true) && ! $this->isPremium() ? 'classic' : $template;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Authors who are not blocked. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('blocked_at');
    }

    /**
     * Every registered user is an author of this blog, so all of them may open
     * the admin unless the admin has blocked them. Filament checks this at
     * sign-in and on every request, so a block also ends an open session.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isBlocked();
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

    /**
     * The public channel's @username without the @, when there is one. A
     * private invite link (t.me/+...) has none, and neither do comments.
     */
    public function channelUsername(): ?string
    {
        $channel = trim((string) $this->telegram_channel);
        $name = preg_replace('~^(?:https?://)?(?:t\.me|telegram\.me)/~i', '', ltrim($channel, '@'));
        $name = explode('/', explode('?', (string) $name)[0])[0];

        // t.me's own paths (invite links, sticker sets) are not channels.
        $reserved = ['joinchat', 'addstickers', 'addemoji', 'share', 'proxy', 'socks', 'setlanguage', 'login'];

        return preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $name) && ! in_array(strtolower($name), $reserved, true)
            ? $name
            : null;
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }
}
