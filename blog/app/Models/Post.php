<?php

namespace App\Models;

use App\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy(PostObserver::class)]
class Post extends Model
{
    use HasFactory;

    public const TYPE_ARTICLE = 'article';
    public const TYPE_GALLERY = 'gallery';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_POLL = 'poll';

    public const SOURCE_WEB = 'web';
    public const SOURCE_TELEGRAM = 'telegram';

    protected $fillable = [
        'user_id', 'title', 'slug', 'html', 'excerpt', 'post_type', 'status',
        'source', 'external_id', 'telegraph_url', 'cover_image', 'published_at',
        'pinned_at', 'series_id', 'series_position', 'telegram_message_id', 'poll',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'telegram_notified_at' => 'datetime',
            'pinned_at' => 'datetime',
            'announce_pending' => 'boolean',
            'reactions' => 'array',
            'poll' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->slug ??= static::uniqueSlug($post->title, $post->user_id);
        });

        // Admin posts often leave the excerpt empty; listings still need one.
        static::saving(function (Post $post) {
            if (blank($post->excerpt) && filled($post->html)) {
                $post->excerpt = static::excerptFrom($post->html);
            }

            // Published with no date means "now"; without one it would never show.
            if ($post->isPublished() && $post->published_at === null) {
                $post->published_at = now();
            }
        });

        // Hashtags in the body become topics. They are only ever added here, so
        // a topic set in the admin survives an edit that does not mention it.
        static::saved(function (Post $post) {
            if ($post->user_id !== null && ($post->wasRecentlyCreated || $post->wasChanged('html'))) {
                $post->attachTopics(static::hashtagsIn($post->html));
            }
        });

        // An author has one pinned post at most: pinning another unpins the old one.
        static::saved(function (Post $post) {
            if ($post->pinned_at !== null && ($post->wasRecentlyCreated || $post->wasChanged('pinned_at'))) {
                static::query()
                    ->where('user_id', $post->user_id)
                    ->whereKeyNot($post->getKey())
                    ->whereNotNull('pinned_at')
                    ->update(['pinned_at' => null]);
            }
        });
    }

    /**
     * Plain text for listings. Every block (paragraph, heading, list item, line
     * break) becomes its own sentence, so a heading never reads as the start of
     * the paragraph after it: "Heading. First line. One. Two"
     */
    public static function excerptFrom(?string $html, int $limit = 280): string
    {
        // Footnotes ("[^1]" markers and "[^1]: ..." notes) are for the page, not a summary.
        $html = preg_replace('~<p>\s*\[\^[^\]\s]{1,20}\]:[\s\S]*?</p>~i', '', (string) $html);
        $html = preg_replace('~\[\^[^\]\s]{1,20}\]~', '', (string) $html);
        $marked = preg_replace('~(</(?:p|h[1-6]|li|blockquote|div|pre|figure|figcaption)>|<br\s*/?>)~i', "$1\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $marked), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $blocks = collect(preg_split('/\n+/u', $text))
            ->map(fn (string $line) => trim((string) preg_replace('/\s+/u', ' ', $line)))
            ->filter()
            ->values();

        // A stop goes between blocks only; the last one ends as the author wrote it.
        // Closing quotes and brackets may follow the punctuation: «Слово.» or (note.)
        $sentences = $blocks->map(fn (string $line, int $i) => ($i === $blocks->count() - 1
            || preg_match('/[.!?…:;][»”"’)\]]*$/u', $line)) ? $line : $line.'.');

        return Str::limit($sentences->implode(' '), $limit);
    }

    /** Slugs are unique per author, so two authors may both have "hello-world". */
    public static function uniqueSlug(string $title, ?int $userId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (static::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * The hashtags in a body, in order of first appearance: "#travel" and
     * "#Oʻzbekiston" count, "C#", "#1" and page anchors do not.
     *
     * @return list<string>
     */
    public static function hashtagsIn(?string $html): array
    {
        $text = html_entity_decode(strip_tags((string) preg_replace('~<[^>]+>~', ' $0 ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/(?<![\p{L}\p{N}_#&\/])#([\p{L}\p{M}\p{N}_ʻʼ]{2,64})/u', $text, $matches);

        $names = [];
        foreach ($matches[1] as $name) {
            if (preg_match('/^\d+$/', $name)) {
                continue;
            }
            $names[Tag::slugFor($name)] ??= $name;
        }

        return array_values($names);
    }

    /** @param  iterable<string>  $names */
    public function attachTopics(iterable $names): void
    {
        $this->tags()->syncWithoutDetaching($this->topicIds($names));
    }

    /** Sets the topics to exactly these (the admin's field). */
    public function syncTopics(iterable $names): void
    {
        $this->tags()->sync($this->topicIds($names));
    }

    /** @return list<int> */
    private function topicIds(iterable $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $slug = Tag::slugFor((string) $name);
            if ($slug === '' || mb_strlen($slug) > 64) {
                continue;
            }
            $ids[] = Tag::firstOrCreate(
                ['user_id' => $this->user_id, 'slug' => $slug],
                ['name' => ltrim(trim((string) $name), '#')],
            )->id;
        }

        return array_values(array_unique($ids));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedium::class)->orderBy('position');
    }

    /**
     * Title or text contains the words, case-insensitively. Postgres lowers
     * any script; SQLite (tests) only ASCII.
     */
    public function scopeSearch(Builder $query, string $terms): void
    {
        foreach (preg_split('/\s+/u', trim($terms)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            // "!" escapes the wildcards; SQLite has no default escape character.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($word)).'%';
            $query->where(fn (Builder $match) => $match
                ->whereRaw("lower(title) like ? escape '!'", [$like])
                ->orWhereRaw("lower(html) like ? escape '!'", [$like]));
        }
    }

    /**
     * What the reader site may show: published, owned by an author, and due.
     * A post published for a future time stays hidden until then.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Published, but for a time still to come. */
    public function isScheduled(): bool
    {
        return $this->isPublished() && $this->published_at?->isFuture() === true;
    }

    /** Came from Telegram, so it must never be pushed back to Telegram. */
    public function cameFromTelegram(): bool
    {
        return $this->source === self::SOURCE_TELEGRAM;
    }

    /**
     * The secret link that shows this post as readers will see it, published
     * or not. Made on first use; a new one replaces it (the old stops working).
     */
    public function previewUrl(bool $renew = false): string
    {
        if ($renew || blank($this->preview_token)) {
            $this->forceFill(['preview_token' => Str::random(40)])->saveQuietly();
        }

        $site = $this->user?->siteUrl() ?? rtrim((string) config('app.frontend_url'), '/');

        return $site.'/preview/'.$this->preview_token;
    }

    /** The post in its public channel, where readers comment; null for private channels. */
    public function telegramPostUrl(): ?string
    {
        $channel = $this->user?->channelUsername();

        return $channel && $this->telegram_message_id ? "https://t.me/{$channel}/{$this->telegram_message_id}" : null;
    }

    /** One more view today, for the post's total and the admin's daily stats. */
    public function recordView(): void
    {
        $this->newQuery()->whereKey($this->getKey())->increment('views_count');

        $day = now()->toDateString();
        $updated = \Illuminate\Support\Facades\DB::table('post_views')
            ->where('post_id', $this->getKey())
            ->where('day', $day)
            ->increment('views');

        if ($updated === 0) {
            \Illuminate\Support\Facades\DB::table('post_views')->insertOrIgnore(['post_id' => $this->getKey(), 'day' => $day, 'views' => 1]);
        }
    }

    public function url(): string
    {
        if ($this->user !== null) {
            return $this->user->siteUrl().'/posts/'.$this->slug;
        }

        return rtrim((string) config('app.frontend_url', config('app.url')), '/').'/posts/'.$this->slug;
    }
}
