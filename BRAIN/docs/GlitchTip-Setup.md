# GlitchTip (Sentry-compatible) Error Monitoring — Setup

GlitchTip speaks the Sentry protocol, so we use the official **`sentry/sentry-laravel`**
SDK pointed at a GlitchTip DSN.

- Stack: Laravel 11, PHP 8.2, Filament 3.2
- GlitchTip MCP available to Claude is **read-only** (list/triage issues, stats).
  Creating the project / DSN must be done once in the GlitchTip web UI.

---

## 1. Create the GlitchTip project (UI — one time)

1. Open GlitchTip → choose the organization (e.g. `coredigify`).
2. **Create Project** → Platform: **Laravel** → Name: `MDBirojs`.
3. Open the project → **Settings → Client Keys (DSN)** → copy the **DSN**.
   It looks like: `https://<key>@<your-glitchtip-host>/<project-id>`.

## 2. Install the SDK (in the container / local PHP env)

`composer install` on deploy uses `composer.lock`, so the SDK must be added to
`composer.json` **and** `composer.lock` and committed.

```bash
composer require sentry/sentry-laravel
# optional: publish config/sentry.php for fine-tuning
php artisan sentry:publish --dsn="<DSN from step 1>"
```

Then **commit** the updated `composer.json` + `composer.lock` (+ `config/sentry.php`
if published) so Coolify installs the package on the next deploy.

> The exception hook is already wired in `bootstrap/app.php`
> (`\Sentry\Laravel\Integration::handles($exceptions)`, guarded by `class_exists`),
> so no extra wiring is needed — it activates automatically once the package exists.

## 3. Configure environment (Coolify env vars + local `.env`)

```env
SENTRY_LARAVEL_DSN=<DSN from step 1>
SENTRY_TRACES_SAMPLE_RATE=0.0        # errors only to start; raise for perf tracing
SENTRY_ENVIRONMENT=production
```

Set these in **Coolify → app → Environment variables** (production) and in local `.env`.
Empty `SENTRY_LARAVEL_DSN` = monitoring disabled (safe default).

## 4. Verify

```bash
php artisan sentry:test          # sends a test event to GlitchTip
```

A new issue should appear in the GlitchTip project within a few seconds.

---

## Notes

- **Only report from production:** keep `SENTRY_LARAVEL_DSN` empty in local/dev,
  or gate via `config/sentry.php`. Errors won't be sent without a DSN.
- **PII / GDPR:** GlitchTip projects scrub IP by default (`scrubIPAddresses`).
  Avoid sending request bodies with personal data; review `send_default_pii`
  in `config/sentry.php` (keep it `false`).
- **Monitoring from here:** once events flow, Claude can triage them via the
  GlitchTip MCP — e.g. list unresolved issues, fetch the latest event/stacktrace,
  and mark issues resolved.

## Status

- [x] `bootstrap/app.php` — Sentry exception hook wired (guarded, safe no-op)
- [x] `.env.example` — `SENTRY_LARAVEL_DSN` / `SENTRY_TRACES_SAMPLE_RATE` documented
- [ ] GlitchTip project created + DSN obtained (UI — manual)
- [ ] `composer require sentry/sentry-laravel` run and committed
- [ ] `SENTRY_LARAVEL_DSN` set in Coolify production env
- [ ] `php artisan sentry:test` verified
