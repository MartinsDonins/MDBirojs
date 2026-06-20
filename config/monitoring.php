<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend (browser) error monitoring — GlitchTip / Sentry
    |--------------------------------------------------------------------------
    |
    | The DSN is public by design (it ships in client-side JS), so the value is
    | injected into the page at runtime by the server rather than baked into the
    | Vite bundle at build time. That keeps it working inside the Docker asset
    | build, which has no access to runtime env vars.
    |
    | Leave VITE_SENTRY_DSN empty to disable frontend reporting (safe default).
    |
    */

    'frontend_dsn' => env('VITE_SENTRY_DSN'),

    'frontend_environment' => env('VITE_SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

];
