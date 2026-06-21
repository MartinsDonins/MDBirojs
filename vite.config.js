import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { sentryVitePlugin } from '@sentry/vite-plugin';

export default defineConfig({
    build: {
        // Emit source maps so the Sentry plugin can upload them; the plugin
        // deletes the .map files after upload so they are never served publicly.
        sourcemap: true,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        // GlitchTip source-map upload. No-ops unless SENTRY_AUTH_TOKEN is set
        // (e.g. local builds), so dev/local builds are unaffected. Must be last.
        sentryVitePlugin({
            org: process.env.SENTRY_ORG,
            project: process.env.SENTRY_PROJECT || 'mdbirojs',
            url: process.env.SENTRY_URL,
            authToken: process.env.SENTRY_AUTH_TOKEN,
            sourcemaps: {
                filesToDeleteAfterUpload: ['./public/build/**/*.map'],
            },
            // The release is auto-detected and injected into the bundle, so the
            // browser SDK reports the same release the maps were uploaded under.
            telemetry: false,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
