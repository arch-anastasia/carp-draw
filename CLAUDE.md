# CarpDraw AI — technical conventions

Read [AGENTS.md](AGENTS.md), [PROJECT.md](PROJECT.md), and [MEMORY.md](MEMORY.md) first.
AGENTS.md governs permissions, shared memory, communication, and decision authority.
The technical conventions below are inherited from the reviewed source and apply
within the authorized task; they do not establish that existing code meets every invariant.

Laravel 12 + Inertia v2 + Vue 3.5 (TS strict) + Tailwind v4 build tooling.
The architecture mirrors the hairstyleatlas.com boilerplate; when in doubt,
check sibling files for the established pattern.

## Rules

- **Two frontends, one design system.** Marketing = server-rendered Blade
  under `resources/views/marketing` with behavior in `resources/js/marketing.ts`
  via `data-*` hooks — NO inline `<script>`/`onclick` (production CSP is
  `script-src 'self'`). Workspace = Inertia Vue pages under
  `resources/js/Pages`, noindexed. Both import `resources/css/carpdraw.css`;
  its semantic classes are the design contract — do not restyle with utility
  soup, reuse the existing classes.
- **Vue**: `<script setup lang="ts">` first, `defineProps<{...}>()`, `useForm`
  for forms, `route()` from ziggy-js, imports via `@/`. Types for shared props
  live in `resources/js/types/`.
- **PHP**: FormRequest classes for validation (never inline for multi-field
  writes), named routes + `route()`, constructor property promotion, explicit
  return types, `casts()` method, `env()` only inside config files. Services
  hold domain logic; controllers stay thin; single-purpose invokable Actions
  for Stripe webhook events; queued jobs should re-fetch fresh state and provide
  idempotent effects. The intake report records gaps requiring verification.
- **Money and credits**: integers in minor units, never floats. The credit
  ledger is append-only — a correction is a new row; every write goes through
  `CreditLedger` inside a locked transaction. Never add a balance counter
  column.
- **Uploads/outputs**: private disk only, served via presigned URL or the
  ownership-checked `projects.image` route. Never public storage.
- **Providers**: only `ProviderRegistry` knows driver names. The fake
  stand-in must never serve production (`isLive()` guards it).
- **Listeners** are registered explicitly in `AppServiceProvider::boot()` —
  event auto-discovery is off in `bootstrap/app.php`. Route registration
  order there is load-bearing (marketing last).
- **Errors**: expected failures are calm flash messages; unexpected ones go
  through `App\Services\ErrorReporter` (log → Sentry → PostHog, scrubbed).
  Nothing in the reporter may throw.

## Commands

```bash
composer dev                                   # serve + queue + logs + vite
php artisan test --compact [--filter=name]     # always test changes
./vendor/bin/pint --dirty                      # before finalizing
./vendor/bin/phpstan analyse --memory-limit=1G # keep at zero errors
npm run build                                  # vue-tsc + vite (CI gate)
npm run test                                   # vitest
```

- Most tests should be Feature tests; use factories and their states
  (`User::factory()->passwordless()`, `->admin()`; `Project::factory()->succeeded()`).
  `Tests\TestCase` has `createSubscribedUser()` / `addSubscription()`.
- Follow AGENTS.md for shared-memory and current-state updates; avoid unnecessary documentation files.
- Do not change dependencies without approval.
