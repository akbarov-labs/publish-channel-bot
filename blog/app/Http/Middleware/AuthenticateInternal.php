<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards calls the bot makes for the platform as a whole rather than for one
 * author (page requests). The bot presents BOT_INTERNAL_SECRET as a bearer
 * token. Caddy also refuses /api/internal/* from outside, but the blog sits on
 * the shared edge network, so the secret is what actually keeps others out.
 */
class AuthenticateInternal
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('telegram.internal_secret');

        // Not configured: the feature is off, as if the route did not exist.
        if ($expected === '') {
            abort(404);
        }

        // Both sides hashed, so the comparison is constant length.
        if (! hash_equals(hash('sha256', $expected), hash('sha256', (string) $request->bearerToken()))) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
