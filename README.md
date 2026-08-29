# CarpDraw AI

Technical drawings from photos and 3D models. A Laravel 12 + Inertia v2 + Vue 3
application: a public Blade marketing site, an authenticated workspace with
three guided creation workflows (Photo to Drawing, 3D Model to Drawing, Create
Specification), a credit-metered generation pipeline, and Stripe subscriptions.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+), Cashier (Stripe), Socialite (Google/Apple),
  SEOTools + spatie/sitemap, Sentry, PostHog (all observability env-gated off by default)
- **Frontend**: Inertia v2 + Vue 3.5 + TypeScript strict, Vite 7, Tailwind v4
  (build tooling — the design system is CarpDraw's own CSS in `resources/css/carpdraw.css`)
- **Two surfaces**: SEO'd Blade marketing pages (`resources/views/marketing`,
  enhanced by the vanilla-TS `resources/js/marketing.ts`, CSP `script-src 'self'`)
  and a noindexed Inertia SPA for the workspace (`resources/js`)
- **Data**: UUIDv7 keys everywhere; credits as an append-only ledger
  (`credit_transactions`, balance = SUM(amount)); uploads and generated sheets
  on a private disk

## Quick start (zero dependencies)

```bash
composer setup      # install, .env, key, migrate (sqlite), npm install + build
composer dev        # serve + queue + logs + vite, concurrently
```

Open http://localhost:8000. Sign in with any email — with `MAIL_MAILER=log`
the secure sign-in link lands in `storage/logs/laravel.log`.

The default `DRAWING_PROVIDER=fake` runs the whole pipeline (upload → credits →
queued job → result) with bundled sample sheets, so nothing costs money. Switch
to `gemini` or `openai` with a real API key for live generation. The registry
refuses stand-in providers in production.

## Docker (production parity)

```bash
docker compose up -d     # php-fpm, nginx :80, postgres, redis, mailpit :8025, queue, vite :5173, stripe-cli
```

Point `.env` at the containers (`DB_CONNECTION=pgsql`, `DB_HOST=postgres`,
`REDIS_HOST=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`,
`SESSION_DRIVER=redis`, `MAIL_HOST=mailpit`). `make help` lists the shortcuts.
The `stripe` service forwards webhooks to `/stripe/webhook` when
`STRIPE_SECRET` is set.

## Tests & quality gates

```bash
php artisan test --compact      # feature suite (sqlite :memory:, fake provider)
npm run test                    # vitest component suite
./vendor/bin/pint --dirty       # PHP formatting
./vendor/bin/phpstan analyse --memory-limit=1G   # larastan, level 5, zero errors
npm run lint                    # eslint
npm run build                   # vue-tsc + vite
```

CI (`.github/workflows/ci.yml`) runs all of the above.

## Architecture notes

- **Credits**: granted at account creation (12 free), spent inside a locked
  transaction when a workflow is queued, refunded from the ledger (idempotent)
  when a generation fails. Subscribers roll over monthly to their plan's
  allowance (Maker 40 / Studio 250) — see `App\Services\Drawing\CreditLedger`.
- **Plans**: `config/stripe.php` is the plan catalogue; live prices come from
  the Stripe Prices API with cached fallbacks. `entitlements` is the
  machine-readable part (3D model processing is Studio-only).
- **Generation**: `App\Jobs\GenerateDrawing`, one per project;
  provider registry in `App\Services\Drawing\ProviderRegistry`
  (fake/gemini/openai). Prompts encode the White Technical Furniture drawing
  standard (`config/drawing.php` + `design-reference/style_presets`).
- **Auth**: passwordless-first. The marketing modal and the sign-in page post
  an email; new accounts get a signed set-password link, existing password
  accounts get a one-time magic link. Google/Apple buttons appear only when
  their credentials are configured.
- **SEO**: everything canonical is built from `APP_URL` (`PinUrlRoot`,
  `CanonicalUrl`); `<x-seo>` drives SEOTools; sitemap/robots/llms.txt are
  routes in `routes/seo.php`.

## Repo history

The previous Next.js interface preview lives in git history (branch `main`
before `laravel-rewrite`). Design source material — generated 3D models,
drawing-style presets, Python export scripts — is kept in `design-reference/`.
