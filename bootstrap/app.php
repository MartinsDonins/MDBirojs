<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectTo(
            guests: '/admin/login',
            users: '/admin'
        );
        $middleware->alias([
            'coredigify.auth' => \App\Http\Middleware\CoreDigifyApiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // GlitchTip / Sentry error monitoring.
        // Safe no-op until `sentry/sentry-laravel` is installed AND SENTRY_LARAVEL_DSN is set.
        // Setup: BRAIN/docs/GlitchTip-Setup.md
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return redirect()->route('filament.admin.auth.login');
        });
    })->create();
