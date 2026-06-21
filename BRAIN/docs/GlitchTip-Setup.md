# GlitchTip (Sentry-compatible) Error Monitoring — Setup

GlitchTip speaks the Sentry protocol, so we use the official Sentry SDKs pointed
at GlitchTip DSNs. Both backend (PHP) and frontend (browser JS) errors are
reported, and frontend stack traces are de-minified via uploaded source maps.

- **Stack:** Laravel 12, PHP 8.3, Filament 3.3, Vite 7
- **Host:** `glitchtip.coredigify.com`, org `coredigify`
- **Projects:**
  | Layer | GlitchTip project | id | slug |
  |---|---|---|---|
  | Backend (Laravel) | MDBirojs Beckend | 20 | `mdbirojs-beckend` |
  | Frontend (browser) | MDBirojs Frondend | 19 | `mdbirojs` |
- The GlitchTip **MCP** available to Claude is read/triage (list issues, fetch
  events/stacktraces, resolve). Creating projects + DSNs must be done in the UI.

> ### ⚠️ DSN vs auth token (the #1 gotcha)
> Three different secrets, easy to confuse — they are NOT interchangeable:
> - **DSN client key** — per project, from *Project → Settings → Client Keys (DSN)*.
>   Public by design (ships in client JS / used by the SDK to send events).
>   Copy the **full DSN verbatim**; do not hand-assemble it.
> - **Auth token** — long token from *Profile → Auth Tokens*. Used only to
>   **upload source maps** at build time. Needs scope `project:releases`.
> - These are distinct values. Putting the DSN key in `SENTRY_AUTH_TOKEN` (or
>   vice-versa) makes events get rejected (401) or source-map upload fail —
>   both **silently**.

---

## A. Backend errors (Laravel)

SDK: **`sentry/sentry-laravel`** (`^4.26`).

1. **Exception hook** — already wired in `bootstrap/app.php`
   (`\Sentry\Laravel\Integration::handles($exceptions)`, guarded by
   `class_exists`), so it activates automatically once the package is installed.

2. **Install** (committed to `composer.json` + `composer.lock`; Coolify runs
   `composer install` from the lock on deploy):
   ```bash
   composer require sentry/sentry-laravel
   ```

3. **Environment** — Coolify production env + local `.env`:
   ```env
   SENTRY_LARAVEL_DSN=<DSN of project 20, from Client Keys>
   SENTRY_TRACES_SAMPLE_RATE=0.0     # errors only to start
   SENTRY_ENVIRONMENT=production
   ```
   Empty `SENTRY_LARAVEL_DSN` = backend reporting disabled (safe default; keep it
   empty locally so dev errors are not sent).

4. **Verify** (in the production container):
   ```bash
   php artisan config:clear     # if the DSN was added after config:cache
   php artisan sentry:test      # sends a test event
   ```
   A new issue appears in project `mdbirojs-beckend` within seconds.

---

## B. Frontend errors (browser JS)

SDK: **`@sentry/browser`** (`^9.47`).

The frontend is a Laravel + Vite + Tailwind app whose real UI is the **Filament
admin panel** (Livewire/Blade), so Sentry is loaded into the panel via a render
hook rather than a SPA entry point.

**Files:**
- `resources/js/sentry.js` — `Sentry.init(...)`; reads the DSN from a runtime
  global. Exposes `window.sentryTest()` and auto-fires on a `?sentry_test`
  query param (verification helper).
- `resources/js/app.js` — imports `./bootstrap` and `./sentry`.
- `config/monitoring.php` — `frontend_dsn` / `frontend_environment` from
  `VITE_SENTRY_DSN` / `VITE_SENTRY_ENVIRONMENT`.
- `resources/views/filament/hooks/sentry.blade.php` — injects the DSN as a
  `window.__SENTRY_DSN__` global and loads `app.js` via `@vite`.
- `app/Providers/Filament/AdminPanelProvider.php` — registers the
  `panels::head.end` render hook returning that view.

> **Why runtime DSN injection (not `import.meta.env`)?** Vite inlines
> `import.meta.env.VITE_*` at **build time**, but the Docker asset build stage
> has no access to Coolify runtime env vars. So the server injects the DSN into
> the page at request time instead. The DSN is public, so this is safe.

**Environment** — Coolify production env (runtime):
```env
VITE_SENTRY_DSN=<DSN of project 19, from Client Keys>
VITE_SENTRY_ENVIRONMENT=production
```
Empty `VITE_SENTRY_DSN` = frontend reporting disabled (safe default).

**Verify:** open `https://<host>/admin/login?sentry_test=1` (the login page also
loads the hook, so no auth needed), or run `window.sentryTest()` in the console.
An issue appears in project `mdbirojs`.

---

## C. Frontend source maps (readable stack traces)

Without source maps, frontend stack traces point at minified `app-<hash>.js`.
We upload source maps to GlitchTip at build time so traces show the original
`resources/js/*.js` with source context.

Tooling: **`@sentry/vite-plugin`** (`^5.3`, devDependency).

- `vite.config.js` — `build.sourcemap: true` + `sentryVitePlugin({...})`. The
  plugin **no-ops when `SENTRY_AUTH_TOKEN` is absent** (local/dev builds are
  unaffected), uploads `.map` files when the token is present, and **deletes the
  `.map` files after upload** so they are never served publicly. It also injects
  the release so the browser SDK reports the same release the maps were uploaded
  under.
- `Dockerfile` (assets stage) — declares `SENTRY_AUTH_TOKEN` / `SENTRY_ORG` /
  `SENTRY_URL` / `SENTRY_PROJECT` as build `ARG`s + `ENV` so the upload runs
  during `npm run build`.

> **Build variables, not just runtime env.** Source-map upload happens *during
> the Docker build*, so these four must be exposed as **Coolify BUILD variables**
> (the "Build Variable" toggle), not only runtime env vars. Otherwise the build
> succeeds but logs `No auth token provided. Will not upload source maps.`

**Coolify BUILD variables:**
| Variable | Value |
|---|---|
| `SENTRY_AUTH_TOKEN` | *(long auth token, scope `project:releases`)* |
| `SENTRY_ORG` | `coredigify` |
| `SENTRY_URL` | `https://glitchtip.coredigify.com/` |
| `SENTRY_PROJECT` | `mdbirojs` (frontend project; defaults to this if unset) |

**Verify:** after a deploy with the build variables set, the build log shows
`[sentry-vite-plugin]` upload lines (not the "No auth token" warning). Trigger a
frontend event (`?sentry_test=1`); its stack trace in GlitchTip should show
`../resources/js/sentry.js` with source lines instead of `app-<hash>.js`.

---

## Notes

- **PII / GDPR:** GlitchTip projects scrub IP by default (`scrubIPAddresses`).
  Backend `send_default_pii` stays `false`; frontend init sets `sendDefaultPii:
  false`. Avoid attaching request bodies with personal data.
- **Test trigger in prod:** `window.sentryTest()` / `?sentry_test=1` stay in the
  bundle for re-verification. They are inert unless explicitly invoked. Remove
  them from `resources/js/sentry.js` if a fully clean prod bundle is preferred.
- **Triage from here:** Claude can list/triage issues and fetch de-minified
  stacktraces via the GlitchTip MCP, and resolve issues.
- **Local installs:** this Windows host's TLS interception breaks local
  `npm install` / `composer` (cert errors); run them inside a Docker container
  instead — the clean container network bypasses it.

## Status

**Backend**
- [x] `bootstrap/app.php` — Sentry exception hook wired (guarded, safe no-op)
- [x] `sentry/sentry-laravel ^4.26` in `composer.json` + `composer.lock`
- [x] `SENTRY_LARAVEL_DSN` (project 20) set in Coolify; `.env.example` documented
- [x] `php artisan sentry:test` verified — issue `MDBIROJS-BECKEND-1`

**Frontend**
- [x] `@sentry/browser ^9.47` + `resources/js/sentry.js`
- [x] runtime DSN injection (`config/monitoring.php` + `panels::head.end` hook)
- [x] `VITE_SENTRY_DSN` (project 19) set in Coolify
- [x] verified via `?sentry_test=1` — issue `MDBIROJS-1`

**Source maps**
- [x] `@sentry/vite-plugin ^5.3` + `build.sourcemap` + Dockerfile build ARGs
- [x] `SENTRY_AUTH_TOKEN` / `SENTRY_ORG` / `SENTRY_URL` / `SENTRY_PROJECT` set as
      Coolify **build** variables
- [x] verified — `MDBIROJS-2` stack trace resolved to `resources/js/sentry.js`
