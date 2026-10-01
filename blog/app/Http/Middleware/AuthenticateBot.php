<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBot
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('telegram.api_token');
        $provided = (string) $request->bearerToken();

        // hash_equals guards against timing attacks; both sides are hashed so
        // the comparison is constant length regardless of the token supplied.
        if ($expected === '' || ! hash_equals(hash('sha256', $expected), hash('sha256', $provided))) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
