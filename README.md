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

## Journal and publishing API

The editorial system is adapted from HairstyleAtlas (`afea2a3`), with CarpDraw
branding and calls to action. No hairstyle catalog, try-on, onboarding, customer
data, articles, or secrets are copied.

- Public journal: `/blog`; category hubs: `/blog/{slug}`; writers:
  `/blog/authors/{username}`. Drafts/scheduled posts are private; killed posts
  return 410. Scheduled posts become public when their publication time arrives.
- Admins: `/admin/posts`, `/admin/categories`, `/admin/authors`; the Editorial
  navigation item and on-article toolbar provide editing, revisions, scheduling,
  publishing, image uploads/cropping, and retirement. Ordinary accounts cannot edit.
- API: set `BLOG_API_TOKEN`, then send `Authorization: Bearer <token>` to
  `/api/v1/blog/context` first. It documents accepted fields, Markdown directives,
  FAQ requirements, image limits, vocabulary, and all endpoints. The `dated_at`
  field round-trips the editorial modification date; omitted fields stay unchanged. Without a token
  configured the API is closed. Never put the token in a URL or browser bundle.
- `/api/v1/blog/posts`: GET lists/filter/search; POST creates; GET/PATCH/PUT by
  slug or UUID reads/updates; DELETE delists without deleting the record.
  `.../{slug}/preview` returns private, noindex HTML. `POST /api/v1/blog/images`
  accepts raster bytes (multipart or base64), re-encodes WebP and returns media
  metadata; it never fetches a remote URL or local filesystem path.
- `RUNTIME_IMAGES_DISK` defaults to `public`. Use `php artisan storage:link`
  when using that disk; production storage must persist across releases. A
  configured public object-storage disk can be selected instead.
- Optional `INDEXNOW_KEY` enables publication notifications through the existing
  queue. `php artisan blog:indexnow --dry-run` previews a backfill; omit the flag
  only when you intend to notify search engines. Future-dated posts are already
  exposed automatically by the public queries/sitemap when due; the backfill
  command can notify IndexNow after that time.
- SEO: only public articles enter the image sitemap; published article slug changes keep a direct 301 from old URLs (reserved against reuse); category/writer/search
  archives are noindex, draft previews are private/no-store, duplicate journal
  pagination redirects, and reader counts never change editorial dates. Articles
  emit Article, BreadcrumbList and content-derived FAQ/HowTo structured data.

For an approved rollout, build assets, run the additive migrations, configure
persistent image storage and the optional publishing token, then restart queue
workers. No content is seeded automatically and no scheduler is required to make
scheduled posts visible. The source changes alone do not configure live tokens or
publish articles.
