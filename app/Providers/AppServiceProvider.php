<?php

namespace App\Providers;

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
        // 1. Mencegah N+1 Queries Error tersembunyi
        // Jika kode mengambil relasi tanpa 'with', otomatis Error di local
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! app()->isProduction());

        // 2. Setingan Rate Limiting (Mencegah DDoS / Spam Request)
        
        // Batas API Umum: 60 Request per Menit per User/IP
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Batas Auth Login: 5 Percobaan per Menit per IP (Anti-Brute Force)
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });

        // Batas Eksekusi AI: 10 Request per Menit per Admin (Anti-Boting Tagihan AI)
        \Illuminate\Support\Facades\RateLimiter::for('ai.generate', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->user()?->id);
        });
    }
}
