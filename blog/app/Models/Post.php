<?php

namespace App\Models;

use App\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'title', 'slug', 'html', 'excerpt', 'post_type', 'status',
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
            $post->slug ??= static::uniqueSlug($post->title);
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedium::class)->orderBy('position');
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
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').'/posts/'.$this->slug;
    }
}
