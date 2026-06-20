import * as Sentry from '@sentry/browser';

// DSN is injected at runtime by the server (see config/monitoring.php and the
// `panels::head.end` Filament render hook). No DSN → reporting stays disabled.
const dsn = window.__SENTRY_DSN__;

if (dsn) {
    Sentry.init({
        dsn,
        environment: window.__SENTRY_ENV__ || 'production',
        // Errors only to start; GlitchTip is Sentry-protocol compatible.
        tracesSampleRate: 0,
        sendDefaultPii: false,
    });

    // Manual verification hook: open any admin page with ?sentry_test=1 to send
    // a test event to GlitchTip, or call window.sentryTest() from the console.
    window.sentryTest = () => {
        Sentry.captureException(new Error('MDBirojs frontend test event'));
    };

    if (window.location.search.includes('sentry_test')) {
        window.sentryTest();
    }
}
