<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedium extends Model
{
    protected $table = 'post_media';

    protected $fillable = ['post_id', 'url', 'type', 'position'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
