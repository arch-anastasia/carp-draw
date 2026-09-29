# HairstyleAtlas foundation, CarpDraw product

Reviewed source: [HairstyleAtlas `be5f472`](https://github.com/ohnysko/hairstyleatlas.com/tree/be5f472).
CarpDraw starting point: `1d6a07f`.

This is a selective upstream sync, not a replacement of CarpDraw with the hair product.
The repositories are still independent. `upstream-foundation.json` records the reviewed
upstream source and protects CarpDraw's presentation. There is no hidden promise that
future HA commits automatically arrive here.

## Shared foundation

| Area | Result |
| --- | --- |
| Runtime | PHP 8.4, Laravel 12, Inertia 2, Vue 3, Tailwind 4, PostgreSQL 17, Redis queues, Cashier 16. Existing newer CarpDraw packages are not downgraded to older HA versions. |
| Patch updates | Laravel 12.69.3 includes the September 29 security fix. Browser test dependencies are patched; Node 22.12+ or 24 is required. |
| Browser reliability | HA's Inertia navigation-abort handling, single guarded recovery for stale Vite chunks, buffered error reporting and optional lazy browser Sentry. Wired into both actual entry points. No SDK request without a browser DSN. Props, request bodies, credentials, signed URL queries and upload contents are not forwarded. |
| Performance | HA's HTML whitespace middleware, with a UTF-8 corruption fix found by screenshot comparison, preserving scripts, JSON-LD, preformatted text and textareas. Stable asset URLs are no longer cached as immutable; hashed build assets still are. |
| Billing | Existing Cashier subscriptions, webhook deduplication, entitlements and append-only credits retained. HA's `billing:check` verifies configured Stripe prices and fallback drift. Scheduled checks are opt-in. |
| Credit correctness | Account lock covers opening grants and renewal decisions, including stale model instances. Exact renewal boundaries and short months are handled. Refunds cannot credit another account. Production continues refusing a fake drawing provider. |
| Private files | Separate opt-in drawing and backup R2 disks. Public disks/bucket aliases are rejected. New projects persist their disk; old rows retain the legacy disk when new uploads switch to R2. Failed uploads cannot spend credits or create a false success. |
| Backups | HA's upload, size verification, retention, freshness diagnostics and fetch commands. A verified PostgreSQL dump script is supplied and restore-rehearsed. Scheduling stays off until an operator configures it. |
| Editorial/SEO | Existing HA-derived articles, authors, media, content API, previews, canonical URLs, structured data, sitemap, redirects and IndexNow remain. CarpDraw's stronger preview/privacy/slug fixes are retained. |
| Verification | One PR pipeline: PostgreSQL and SQLite, real concurrent spending, delayed Redis queue/worker round trip, actual Nginx routing, dump/restore rehearsal, desktop/mobile browser flows, protected presentation, PHPStan, Pint, ESLint, TypeScript and Vitest. |

### Four audit defects closed

- Nginx now routes `/robots.txt` through Laravel with HTTP 200, not a 404 with a correct body.
- Image storage returning false produces an error, not a 201 pointing at a missing file.
- Scheduled articles enqueue an IndexNow job for their publication time. Jobs recheck visibility
  before announcing, so postponed/killed/renamed articles cannot leak an obsolete URL.
- `cover_image_alt: null` clears the cover description while preserving the cover.

Posts scheduled before this release have no delayed job yet. When enabling IndexNow on an
existing installation, use the
existing `blog:submit-indexnow` command after publication. Do not treat an IndexNow ping as
proof of indexing.

## Deliberately unchanged

- Landing page, public copy, brand assets, CSS, Vue pages, layouts and dashboard mockup.
- Prices, free credit quantity, workflow costs, plan entitlements and subscription economics.
- Existing registration and sign-in UX. No HA onboarding, hair catalog, selfie flow, paywall,
  coupons, trial reminders, analytics consent UI or hair-specific generation providers.
- No Stripe/OAuth/model/Sentry/PostHog keys added. No real generations, purchases or emails
  are used in tests. Private R2 storage and backup scheduling are not enabled by this PR.
- CarpDraw's stronger transactional refunds and create-before-cancel plan changes are kept;
  copying older upstream implementations over them would regress correctness.

The dashboard remains the current CarpDraw view. Backend credits/billing can be extended
later without redesigning this screen as part of the foundation sync.

## Repeatable upstream review

```sh
npm run upstream:check
node scripts/upstream-audit.mjs --source /path/to/latest/hairstyleatlas
```

The first command fails if a protected file changed from the agreed baseline. The second
also detects changes in the reviewed HA source files. Review each drift, port neutral
behavior/tests, keep CarpDraw-specific adapters, and update the source pin/hashes only
when the review is complete. Intentional future design work should explicitly update the
protected baseline in its own approved change, not bypass this check.

## Storage rollout, separately configured

1. Keep `DRAWING_LEGACY_DISK` equal to the disk that existing project files actually use
   (currently the local-disk default). The additive migration does not move or delete files.
2. Configure `R2_DRAWING_BUCKET` as a private bucket, with dedicated credentials if available.
   `r2_drawings` otherwise reuses AWS credentials and endpoint, never the public bucket name.
3. Verify upload, authenticated preview, download and account deletion in staging before
   setting `DRAWING_DISK=r2_drawings`. Existing projects keep their recorded/legacy disk.
4. A rollback may keep the nullable `projects.storage_disk` column. Do not drop it or move
   files just to roll back application code.

## Backup rollout, separately configured

Read-only preflight confirmed existing project files use `DRAWING_DISK=local`, matching
the legacy fallback. Production has its own Nginx configuration, outside this repository;
this PR fixes and tests the repository Nginx configuration, not that independent file.

The repository does not own the existing production compose configuration. Nothing here
silently replaces that server configuration or starts a paid storage operation.

- Run `scripts/backup-postgres.sh` with PostgreSQL 17's client, libpq environment credentials
  and a private `BACKUP_DIR`. It writes a hidden partial file and publishes a scheduled dump
  only after pg_dump status, gzip integrity and completion-marker checks pass.
- Mount the verified dump directory read-only into the scheduler at `BACKUP_LOCAL_PATH`.
- Configure the private `R2_BACKUP_BUCKET`. Keep it separate from public editorial media.
- Run `php artisan backup:sync-r2 --dry-run`, then an authorized first sync and isolated restore.
- Enable `BACKUP_SYNC_ENABLED=true` only after the chain is proved. Monitor nonzero command
  exits: merely scheduling the command is not proof of a fresh backup.
- `backup:fetch-r2` downloads a dump; it does not write to the database. Never restore over a
  production database without explicit approval and a fresh pre-restore dump.

## Local verification

```sh
npm ci && npm run build && npm test
vendor/bin/pint --test
npx eslint resources/js --quiet
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan test --compact
python3 scripts/check-nginx.py
```

Use an isolated PostgreSQL database named `carpdraw_ci` or `carpdraw_foundation_test` and
`APP_ENV=testing` for `php tests/credit-concurrency.php`. It runs eight independent workers.
`scripts/check-backup.sh` takes a disposable PostgreSQL container and restores only into
its own test database. Both reject arbitrary production database names.

Browser fixtures require `APP_ENV=testing`, SQLite `database/e2e.sqlite`, file sessions,
array mail, fake drawing provider and a dummy `BLOG_API_TOKEN`. Migrate, run
`php tests/e2e/seed.php`, start the local server, then `npm run test:e2e`.
Set `E2E_BASELINE_URL` to an equivalent server at the pre-sync commit to compare desktop
and mobile homepage/dashboard screenshots. Moving video pixels are masked, not changed
in the product. Generated screenshots/traces are CI artifacts, not production assets.
