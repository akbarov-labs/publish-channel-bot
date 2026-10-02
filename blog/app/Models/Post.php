<?php

namespace App\Models;

use App\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy(PostObserver::class)]
class Post extends Model
{
    use HasFactory;

    public const TYPE_ARTICLE = 'article';
    public const TYPE_GALLERY = 'gallery';
    public const TYPE_VIDEO = 'video';

    public const SOURCE_WEB = 'web';
    public const SOURCE_TELEGRAM = 'telegram';

    protected $fillable = [
        'user_id', 'title', 'slug', 'html', 'excerpt', 'post_type', 'status',
        'source', 'external_id', 'telegraph_url', 'cover_image', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'telegram_notified_at' => 'datetime',
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
        });
    }

    /**
     * Plain text for listings. Every block (paragraph, heading, list item, line
     * break) becomes its own sentence, so a heading never reads as the start of
     * the paragraph after it: "Heading. First line. One. Two"
     */
    public static function excerptFrom(?string $html, int $limit = 280): string
    {
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedium::class)->orderBy('position');
    }

    /** What the reader site may show: published, dated, and owned by an author. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNotNull('user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Came from Telegram, so it must never be pushed back to Telegram. */
    public function cameFromTelegram(): bool
    {
        return $this->source === self::SOURCE_TELEGRAM;
    }

    public function url(): string
    {
        if ($this->user !== null) {
            return $this->user->siteUrl().'/posts/'.$this->slug;
        }

        return rtrim((string) config('app.frontend_url', config('app.url')), '/').'/posts/'.$this->slug;
    }
}
