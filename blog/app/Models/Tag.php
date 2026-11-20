<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A topic in one author's space, such as #travel. */
class Tag extends Model
{
    protected $fillable = ['user_id', 'name', 'slug'];

    /** "#Travel", "travel " and "TRAVEL" are the same topic. */
    public static function slugFor(string $name): string
    {
        return mb_strtolower(trim(ltrim(trim($name), '#')));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
