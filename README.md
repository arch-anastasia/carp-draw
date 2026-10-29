# CarpDraw

CarpDraw is an early exploration of AI-assisted creation and work with digital models for furniture, architectural components, parts, and fittings. The intended users, useful workflows, and output requirements are still being discovered.

A working showcase has been reported. On October 29, 2026, a supplied source bundle was inspected offline: it contains a Laravel/Vue application with queued image generation, billing and operational tooling. See the [source intake report](docs/source-intake-2026-10-29.md) for verified findings and gaps. The application is now integrated at this repository root with both the original source history and our project records preserved. Local execution with SQLite and the fake drawing provider is verified as of October 29, 2026; see [local development](#local-development). The original showcase, live integrations and production readiness remain unverified.

## Start here

- [Project map](PROJECT.md): current focus, roadmap, open questions, sustainability, and collaboration.
- [Shared memory](MEMORY.md): dated decisions, findings, evidence, rationale, and unresolved follow-up.
- [Handover checklist](docs/HANDOVER.md): ownership, access, and technical discovery, including progress without source access.
- [Source intake report](docs/source-intake-2026-10-29.md): bundle integrity, import recommendation, preliminary architecture and operational risks.
- [Codex instructions](AGENTS.md): how to work in this repository.

## Working with Codex

Open this folder as the project workspace and begin with a bounded request, for example: “Read PROJECT.md and help me prepare a walkthrough of the existing showcase.” Codex uses repository [AGENTS.md instructions](https://learn.chatgpt.com/docs/agent-configuration/agents-md).

For an intentional project review, invoke `$project-review`. The [local skill](.agents/skills/project-review/SKILL.md) proposes evidence-based updates and leaves strategic decisions to people. Its invocation policy follows the [official skill format](https://learn.chatgpt.com/docs/build-skills). If the new skill is not visible, restart Codex in this workspace.

Ask in any language. Live replies follow your current language until you switch; repository records and commit messages remain in English. Each collaborator's conversation language is independent.

## Viewing and editing the plan

Open [PROJECT.md](PROJECT.md) to read Current Focus, then Roadmap. It is an ordinary Markdown file with a Mermaid diagram, so the plan is available without asking Codex. You can also ask: “Show the current scope, dates, blockers, and decisions awaiting us.”

Edit the Markdown directly in the app's file editor or your preferred text editor, then save. The desktop app also supports selecting content and asking for a focused revision through annotations; see [working with files](https://learn.chatgpt.com/docs/artifacts-viewer) and [in-app editing](https://learn.chatgpt.com/docs/changelog). Keep dates consistent between milestone text and the diagram, and label unapproved ideas Proposed. Saving a file and committing it to Git are separate steps.

After manual edits, ask: “Review my PROJECT.md changes, synchronize the shared memory, and commit them in English.” Include the reason for consequential changes so the journal can preserve it. Codex checks the diff, records the evidence and decision authority, and asks only where a consequential ambiguity remains. You can also ask it to propose an edit first.

New work starts from PROJECT.md and MEMORY.md. Codex saves durable decisions, constraints, and material findings, then batches memory with related substantive work in local commits. Routine answers and unaccepted daily suggestions do not need a journal entry or commit. Eligible memory saves need no separate confirmation; a memory-only commit can wait if it would trigger sandbox approval. Personal chats are supplementary. Collaborators share recorded context when they use the same repository revision. No remote synchronization exists yet. The local setup below has been exercised with the locked dependencies; [CD-016](MEMORY.md#cd-016) records the results and limits.

Routine reversible repository work is authorized within the requested task. Outside-repo access, internet use, and sensitive or externally visible actions require scoped approval unless already authorized. Codex's actual sandbox can still protect Git and agent settings; repository instructions do not override it. The [proposed permission configuration](docs/codex-permissions.proposed.toml) is available to review but is not installed. Creating a remote or pushing requires explicit authorization.

## Application status and GitHub preparation

Source main `c0f2b8d79bca66e5f51f67d256070cfed38f652e` is integrated with the existing governance history. The application lives at the root; PROJECT.md, MEMORY.md, AGENTS.md and the handover/intake records remain the shared operating context. CLAUDE.md carries compatible technical conventions under AGENTS.md.

The existing CI and deployment definitions are retained in `.github/workflows-disabled/`. GitHub will not discover them there. The workflow-check script validates those templates. Choose and isolate the new CI runner, review its permissions, and obtain missing deployment configuration before deliberately enabling workflows. No remote, server credentials, or production connection was configured by this import.

A future push of main includes the source main ancestry and the governance ancestry. All 22 non-HEAD advertised source refs are retained locally under `refs/archive/carpdraw/2026-10-29/` (including the original source-main pointer); ordinary main pushes do not publish that archive namespace. Retain the original bundle. If every side-branch/PR commit must also be published, explicitly map those archived refs to an agreed destination namespace rather than using an indiscriminate mirror push.

Confirm the destination owner, visibility and source/asset sharing rights before publication. The import itself makes no remote change. The [intake report](docs/source-intake-2026-10-29.md) records runtime gaps, including unavailable CAD workflows and generation failure cases.

## Application reference

The following technical notes are adapted from the received source. Commands describe the existing workflow; listing them is not permission for Codex to install dependencies, run services, migrate a database, or contact external systems. Use a disposable local configuration and fake provider for the first reproduction. Existing product, price and feature claims still need validation against PROJECT.md.

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

## Local development

Verified on October 29, 2026: macOS arm64, PHP **8.4.26**, Composer **2.11.0**, Node **24.15.0**, npm **11.12.1**, and the existing lockfiles. Use PHP 8.4 for this lockfile: some locked packages require it even though the root manifest says PHP 8.2+. No application dependency versions were changed. The current checkout already has dependencies, an ignored `.env`, an application key and a migrated SQLite database.

### Start or restart this checkout

Run from the repository root. On this Mac, PHP was installed with Homebrew and Composer is an ignored local PHAR at `private/tools/composer.phar`. In each PHP terminal, set these **session-only** variables:

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
export PHP_INI_SCAN_DIR=":$PWD/docker/php"
```

The leading colon preserves PHP's default additional configuration directory. The repository's existing `docker/php/php.ini` supplies 64 MB upload/post limits and 256 MB memory, avoiding Homebrew PHP's 2 MB upload default. No shell profile or system PHP configuration needs editing.

Keep these commands running in three terminals:

```bash
# Terminal 1 — application, bound only to loopback
php artisan serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload

# Terminal 2 — database queue, loading current application code for every job
php artisan queue:listen --sleep=1 --tries=3 --timeout=80

# Terminal 3 — Vite, loopback-only on port 5173
npm run dev
```

Open [CarpDraw](http://localhost:8000). Vite applies frontend changes automatically; ordinary PHP changes apply on the next request. Restart the PHP server and queue listener after changing `.env` or PHP configuration. `--no-reload` keeps the PHP configuration environment available to Artisan's child server; it does not disable Vite updates. Stop each process with **Ctrl+C**. Port conflicts fail explicitly; stop the previous instance instead of letting the app silently move to another port. Keep Vite's advertised host `localhost:5173` aligned with the existing local Content Security Policy.

Use [Create account](http://localhost:8000/signup) to make a local account with an email and password. Local email goes to the ignored `storage/logs/laravel.log`; no email is delivered. The browser smoke check also leaves a synthetic local tester and one saved sample drawing in this checkout's disposable database.

The development profile uses SQLite, database sessions/cache/queues, private local files, `DRAWING_PROVIDER=fake`, and `MAIL_MAILER=log`. Provider, Stripe, OAuth, telemetry and publishing credentials remain empty; backups, billing checks and PostHog remain disabled. Fake generation returns a **bundled sample**, not a drawing inferred from the uploaded object. Paid AI, real billing, OAuth, object storage and production behavior need separate configuration and validation.

The explicit three-process setup uses installed tools only. The inherited `composer dev` helper invokes an undeclared `npx concurrently` package; it is not the verified startup path.

### Prepare a fresh checkout

Install PHP 8.4 with SQLite, GD, mbstring and the extensions required by Composer, Composer 2, and a Vite-compatible Node release (verified above). Acquire dependencies from the existing lockfiles. On this machine, substitute `php private/tools/composer.phar` for `composer`; that PHAR was obtained using the official Composer installer after verifying its SHA-384 checksum.

```bash
# Fresh checkout only; preserve an existing .env and application key.
test -f .env || cp .env.example .env
composer install --prefer-dist --no-interaction
npm ci --no-audit --no-fund
# Only generate a key when APP_KEY is empty.
php artisan key:generate
touch database/database.sqlite
php artisan migrate --no-interaction
npm run build
```

Before migrating, verify `.env` uses `APP_ENV=local`, `APP_URL=http://localhost:8000`, `DB_CONNECTION=sqlite`, the fake provider, log mail, and empty live credentials. Do not set a production `DB_URL` or database path. `.env`, SQLite data, uploads, logs, dependencies and compiled assets are ignored. Avoid `migrate:fresh` unless deliberately discarding local data. These scoped instructions do not authorize production or remote operations.

### Verified checks and limits

`npm run build` and TypeScript passed; all **161 Vitest tests** and **270 PHP tests (1,373 assertions)** passed; PHPStan reported no errors. Vite configuration lint and dirty-file Pint checks passed. Browser testing verified homepage rendering, sign-in, navigation, upload → database queue → sample output, private file existence, and credits changing from 12 to 8. No failed jobs or browser console errors were observed, and a source-file touch triggered a Vite HMR update. Both listening ports were verified on `127.0.0.1` only.

The build emits three font-resolution warnings; the referenced fonts exist under `public/fonts` and loaded successfully in the browser. Dependency installation reported upstream deprecation notices; no dependency advisory audit was performed. The full Playwright suite, PostgreSQL/Redis checks, backup rehearsal, paid providers and deployment remain unexecuted. This is a development environment, not production-readiness evidence.

## Supplied Docker development setup

Review and constrain host ports before starting: the supplied file publishes database, Redis, mail and development-server ports and includes an optional live Stripe forwarder. It is not the missing production configuration.

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

The inherited `.github/workflows-disabled/ci.yml` describes these checks and additional database/queue/browser checks. It is inactive here; no successful run is claimed by the import. `npm run lint` includes `--fix` and changes files.

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

The imported application ancestry includes the previous Next.js interface preview
before the Laravel rewrite. The merge also preserves the original governance history. Design source material — generated 3D models,
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
