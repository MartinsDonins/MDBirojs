@php($sentryDsn = config('monitoring.frontend_dsn'))

@if ($sentryDsn)
    {{-- DSN injected at runtime (public key) so the Vite build needs no env. --}}
    <script>
        window.__SENTRY_DSN__ = @json($sentryDsn);
        window.__SENTRY_ENV__ = @json(config('monitoring.frontend_environment'));
    </script>
    @vite(['resources/js/app.js'])
@endif
