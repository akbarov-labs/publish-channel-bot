<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Posts an author groups into numbered parts. */
class Series extends Model
{
    protected $table = 'series';

    protected $fillable = ['user_id', 'title', 'slug', 'description'];

    protected static function booted(): void
    {
        static::creating(function (Series $series) {
            if (blank($series->slug)) {
                $base = Str::slug($series->title) ?: 'series';
                $slug = $base;
                for ($n = 2; static::where('user_id', $series->user_id)->where('slug', $slug)->exists(); $n++) {
                    $slug = "{$base}-{$n}";
                }
                $series->slug = $slug;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** The parts readers can see, in reading order. */
    public function visibleParts(): HasMany
    {
        return $this->posts()
            ->visible()
            ->orderByRaw('series_position is null')
            ->orderBy('series_position')
            ->orderBy('published_at');
    }
}
