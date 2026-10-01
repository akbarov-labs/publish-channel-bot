<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public face of an author. Lists fields explicitly so that email,
 * tokens and webhook secrets can never leak through the reader API.
 *
 * @mixin User
 */
class AuthorResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'username' => $this->username,
            'name' => $this->name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl(),
            'telegram_url' => $this->telegramUrl(),
            'site_url' => $this->siteUrl(),
            'posts_count' => $this->whenHas('posts_count'),
            'latest_post_at' => $this->whenHas('latest_post_at', fn () => $this->latest_post_at
                ? date(DATE_ATOM, strtotime($this->latest_post_at))
                : null),
        ];
    }
}
