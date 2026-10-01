<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the author a bot request acts for. Each author has their own token
 * (issued from their admin profile); the request's user is that author.
 */
class AuthenticateBot
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->bearerToken();
        $author = User::findByBotToken($provided) ?? $this->legacyAuthor($provided);

        if ($author === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn () => $author);

        return $next($request);
    }

    /**
     * Single-author installs authenticated with TELEGRAM_API_TOKEN from .env.
     * That token keeps working and acts for the first author, so an upgrade
     * does not break a bot that is already connected.
     */
    private function legacyAuthor(string $provided): ?User
    {
        $expected = (string) config('telegram.api_token');

        // hash_equals guards against timing attacks; both sides are hashed so
        // the comparison is constant length regardless of the token supplied.
        if ($expected === '' || ! hash_equals(hash('sha256', $expected), hash('sha256', $provided))) {
            return null;
        }

        return User::orderBy('id')->first();
    }
}
