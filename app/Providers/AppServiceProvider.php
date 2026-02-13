<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\URL;

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
        if (app()->environment('production')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
            
            // Temporary fix: Clear cache to ensure new URL config takes effect
            // \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        }
    }
}
