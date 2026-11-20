<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Views of one post from one address. The site already counts a reader
        // once per visit; this only blunts scripted refreshes. Mobile carriers
        // put many readers behind one address, hence more than one a minute.
        RateLimiter::for('views', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip().'|'.$request->route('username').'/'.$request->route('slug')));
    }
}
