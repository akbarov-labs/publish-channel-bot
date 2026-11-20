<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // In production only the edge Caddy can reach this container (it has
        // no published port), and it terminates TLS. Trusting its forwarded
        // headers is what makes generated URLs - media, the admin's own
        // links - https:// rather than mixed content.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'bot.auth' => App\Http\Middleware\AuthenticateBot::class,
            'internal.auth' => App\Http\Middleware\AuthenticateInternal::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
