<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Rate limit de autenticación (SEC-03): combina identidad+IP e IP sola para
        // frenar fuerza bruta y credential stuffing sin castigar a un tenant entero.
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower((string) $request->input('email'));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)->by($email.'|'.$ip),
                Limit::perMinute(20)->by($ip),
            ];
        });
    }
}
