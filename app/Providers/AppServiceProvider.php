<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        RateLimiter::for('ai_tools', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(3)->by($request->user()->id)
                : Limit::perMinute(5)->by($request->ip());
        });
        
        RateLimiter::for('mock_tests', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(3)->by($request->user()->id)
                : Limit::perMinute(5)->by($request->ip());
        });
        
        RateLimiter::for('forgot_password', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });
        
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return env('FRONTEND_URL', 'http://localhost:5173') . '/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
