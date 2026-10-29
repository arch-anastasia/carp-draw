# CarpDraw source intake — October 29, 2026

This report records the pre-import inspection. The subsequent authorized root integration is recorded in [CD-015](../MEMORY.md#cd-015); its validation does not change the runtime limitations below.

## Assessment

**A controlled local import for inspection and development is reasonable. Running the supplied setup, connecting production services, or publishing the full history is not cleared by this review.** No structural Git corruption, unsafe tree paths, or matching credential tokens were detected in the offline checks. This is a preliminary source review, not a malware certification, dependency vulnerability audit, or production-readiness assessment.

The bundle was unpacked into a private temporary bare repository for inspection. No source history was merged into the existing documentation repository; no checkout hooks, dependency installers, application code, migrations, containers, workflows, or remote services were run. Repository instructions inside the bundle, including `CLAUDE.md`, were treated as source material rather than instructions authorizing actions.

## Evidence identity and scope

| Item | Verified observation |
| --- | --- |
| Input | User-supplied `CarpDraw-full-history.bundle`, inspected October 29, 2026. |
| Size | 173,983,438 bytes, approximately 166 MiB. |
| SHA-256 | `2f5af8aa542898d17fb9383d0533174727d412f5a0b0aec9224f3662b3356821` |
| Reviewed source tip | `main` at `c0f2b8d79bca66e5f51f67d256070cfed38f652e`. |
| Included references | 23 advertised references: 3 branches, 19 pull-request heads, and HEAD; no tag references. |
| History | 71 distinct commits across included refs; 40 reachable from main; one root commit. Main's recorded commit dates span July 30–October 22, 2026. Dates are Git metadata, not independent timestamps. |
| Objects | 887 blobs, 574 trees, 71 commits; 224,434,386 uncompressed object bytes. |
| Main tree | 553 files, 54,542,966 bytes; 449 text files materialized for reading without executable permissions. |
| Integrity | `git bundle verify` succeeded in an empty repository and reports complete history; `git fsck --full --strict --no-reflogs` succeeded without diagnostics. |

“Complete history” means no missing prerequisites for the references contained in this bundle. It does not prove that the sender included every upstream branch, tag, deployment file, or uncommitted change. Sender identity and upstream authenticity were not independently verified.

All source references below identify paths and line numbers **inside the bundle at the reviewed tip**, not files already imported into this documentation workspace.

## Offline import checks

| Check | Result and limit |
| --- | --- |
| Git tree safety | Inspected all 71 commit trees and 723 distinct paths. No symlinks, submodule gitlinks, `.git` path components, parent traversal, absolute paths, or control-character paths detected. Four executable paths are the expected Artisan entry point and three shell scripts. |
| Filters and large-file dependencies | The historical `.gitattributes` has no checkout filter directives. No Git LFS pointer files or `.gitmodules` paths detected. |
| Credential patterns | Scanned all 887 blobs and 71 commits for common provider tokens, private-key markers, credential-bearing URLs, and JWTs. No matches. A supplemental heuristic over UTF-8 text found no non-placeholder literal candidates under its rules. These are bounded patterns, not proof that every possible secret or sensitive document is absent. |
| Embedded archives | Inspected 18 distinct ZIP/tar.gz blobs, including historical `.codex-site*` exports. Examined 1,227 member files / 186,279,986 unpacked bytes, deduplicating content for token checks. No traversal, special/link members, sensitive-name flags, token matches, archive errors, or inspection-limit skips. No archive members were executed. |
| Private material | No tracked `.env` other than `.env.example`, database dump, or private-key path detected in Git trees. Personal contact fields and named design/reference assets are present; values are deliberately omitted here. Confirm their sharing rights before publishing history. Image/video contents were not visually or forensically audited. |
| Install behavior | Composer setup installs packages, generates keys, migrates the configured database, and builds assets. Composer's autoload hook boots Artisan package discovery. No root npm install lifecycle scripts were found in historical package manifests, but locked esbuild/fsevents packages have install scripts. Importing files does not authorize these actions. |
| Dependencies | npm's root lock declarations match package.json; 386 locked npm entries resolve through the npm registry. Composer locks 111 production and 38 development packages. Package payloads were not installed or audited; no current advisory service was queried. |
| Provenance | Source documentation describes reuse from HairstyleAtlas. Composer declares MIT, but no LICENSE/COPYING/NOTICE file was found in the inspected Git paths. Rights to inherited code, brand assets, reference images/models, and historical exports remain unconfirmed. A manifest declaration is not independent permission evidence. |

The review used an empty Git template, disabled hooks and inherited user/system Git configuration in its temporary repository, restricted Git transport to the supplied local file, and read object contents directly. It did not alter the user's normal Git configuration or the inactive Codex permission proposal.

## System found in the code

This is a Laravel monolith with two web surfaces, not a separately deployed frontend/backend service pair in the supplied source. PHP routes and controllers serve Blade marketing pages and an Inertia/Vue workspace. A queued job sends uploaded image bytes to a configured external image provider and stores a raster result privately.

| Area | Source evidence | What remains unverified |
| --- | --- | --- |
| Backend | Laravel 12.69.3, Inertia Laravel 2.0.25, Cashier 16.7.0 locked in composer.lock; composer.json requires PHP ^8.2; Docker pins PHP 8.4.27. | Successful installation, boot, and compatibility of the actual deployment. |
| Frontend | Vue 3, TypeScript, Vite 7, Tailwind 4; Blade marketing plus Inertia workspace. | Browser behavior, build success, accessibility, and real-user usefulness. |
| Persistence | Eloquent models/migrations, account/project/credit ledger tables; local SQLite default; PostgreSQL 17 and Redis configuration for containers/CI. | Actual schema, production data, service ownership, restore capability. |
| Generation | `ProjectService` stores input and spends credits; `GenerateDrawing` calls an image provider; adapters exist for Gemini, OpenAI, OpenRouter, and a local fake. | Provider contract compatibility, credentials, output fidelity, billed costs, throughput. |
| Auth and access | Password, signed links, Google/Apple OAuth code; authenticated project routes and explicit ownership checks; separate admin and token-gated editorial API. | End-to-end authentication security and deployed proxy/session configuration. |
| Billing | Stripe subscriptions, plan entitlements, credit grants/spend/refunds, billing diagnostics. | Live account access, price configuration, webhook history, and approval of this economic model. Existing code does not adopt a business strategy for the founder/partner. |
| Operations | Docker definitions; GitHub workflows; backup tooling; optional private R2 disks; Sentry/PostHog hooks. | Server configuration and access, active integrations, billing owners, responder, logs and recovery evidence. |
| Tests | 30 PHP `*Test.php` files, 11 Vitest test files, one Playwright spec, plus credit-concurrency and queue-roundtrip scripts. CI describes PostgreSQL/SQLite, Redis, backup/restore and browser checks. | No test suite was executed here; supplied claims about earlier successful runs remain Reported. |

Positive safeguards visible in code include authenticated ownership checks (`ProjectController.php:45`, `:74`, `:104`), private disk checks (`PrivateDiskGuard.php:10`), account locks around credit decisions (`ProjectService.php:47`, `:116`), ledger-derived idempotent refunds (`CreditLedger.php:65`), and a production check refusing a fake/unconfigured provider (`ProjectService.php:79`). These are useful foundations; they are not substitutes for runtime verification.

## Priority findings

### 1. Narrow the runnable product scope to what the providers support

**Verified in source; high product-validation priority.** `config/drawing.php:56` accepts SKP/3DS/OBJ/DWG for model drawing, while `ProviderRegistry.php:111` accepts only raster formats for real providers. `ProjectService.php:94` rejects unsupported sources before charging. Therefore the configured 3D upload formats cannot reach a live generation through this path; the fake provider accepts them and can conceal that gap in a demonstration. `PhotoToModel.vue:20` explicitly says Coming soon.

The observed generation path returns PNG/JPEG/WebP image data. Plan configuration advertises PDF/SVG/DXF capabilities (`config/stripe.php:37`, `:62`), but no corresponding export implementation was identified in the reviewed workflow. Treat editable CAD/export capability as unverified. The default concept prompt explicitly avoids manufacturing claims, while the selectable “fabrication sheet” prompt requests all workshop dimensions (`config/drawing.php:149`, `:161`). These need one consistent product promise and acceptance criteria.

**Recommendation:** First reproduce photo-to-concept drawing with known inputs. Decide which unavailable workflows/claims to hide or implement before user testing; do not infer geometric or manufacturing correctness from an attractive generated image.

### 2. Storage retries can repeat paid image generation

**Verified control flow; high operational priority before paid trials.** `GenerateDrawing.php:72` calls the provider before saving its output. A failed output write becomes retryable at line 85; line 107 releases the entire job, which starts again by calling the provider. The configured job permits three attempts. A successful provider response followed by a storage outage can therefore generate multiple requests for one project, even though customer credit refunds are idempotent. Provider usage/request metadata is returned by adapters but is not persisted by this job.

**Recommendation:** Separate provider completion from storage retry, retain a durable attempt/result reference where supported, and record request IDs and usage. Test storage failure after provider success and ambiguous provider timeouts with fakes before spending money. Customer credits do not themselves cap provider spend.

### 3. Generation needs a claim and cancellation boundary

**Code-derived concurrency risk; runtime reproduction pending.** `GenerateDrawing.php:52` reads status and line 59 marks processing without an atomic claim or job-overlap lock. Concurrent deliveries can both pass the unfinished-state check. `ProjectController.php:83` refunds and deletes an unfinished project while a running worker may already hold its source bytes. The worker does not recheck cancellation/existence before writing output at line 84. That creates a path to extra provider calls and late orphaned output after deletion.

**Recommendation:** Define ownership of an active attempt, cancellation behavior, and a guarded completion transition. Reproduce duplicate delivery and delete-during-generation; verify both ledger results and stored-file cleanup.

### 4. Check the database-to-queue failure boundary

**Hypothesis requiring a failure-injection test.** `ProjectService.php:139` spends credits within a database transaction and line 141 dispatches after commit. The outer exception handler deletes the source at line 147 regardless of whether the transaction already committed. A queue publication failure after commit may leave a charged, queued project without a job or source. No reconciliation/outbox path was identified in this preliminary review.

**Recommendation:** First reproduce a queue failure immediately after commit against the chosen queue driver. Then select the smallest recoverable publication/reconciliation approach; do not add infrastructure before the failure is understood.

### 5. The supplied Docker setup is not an isolated first-run profile

**Verified configuration; address before starting it.** `docker-compose.yml:45` publishes PostgreSQL and line 62 publishes Redis without a loopback bind. Redis has no password configured there; PostgreSQL has a development password fallback. Other services expose mail, web and Vite ports. The Node service runs `npm install` on startup, and the Stripe service forwards webhooks when credentials are supplied. The “production parity” README label does not establish a safe production deployment.

**Recommendation:** Use a reviewed local override with loopback-only required ports, disposable data, fake generation, empty live credentials, and disabled billing/telemetry/backup integrations. Keep installs and service startup explicitly authorized.

### 6. Deployment handover is incomplete

**Verified source references; live configuration Unknown.** `.github/workflows/deploy-production.yml:27` uses a self-hosted runner and opens an SSH session as root, relying on an externally configured forced command `/srv/deploy/deploy.sh`. That script is absent from the bundle. `docs/ha-foundation.md:79` also says production has independent Nginx and Compose files. CI uses the same `self-hosted, carpdraw` labels, but runner isolation and secret separation were not inspected. Deployment triggers on a main push and is not visibly chained to the CI workflow; actual branch/environment protection is Unknown.

**Recommendation:** Obtain sanitized deployment files, runner topology, protection rules, rollback instructions, and account owners. Keep GitHub Actions disabled in any new destination until reviewed. Do not connect existing runner or deployment secrets as part of source import.

## Validation performed and limits

Passed: Git bundle verification and strict object/connectivity checks; all-history path/mode review; bounded credential and archive checks; parsing of nine JSON files and nine Python files; syntax-only checks of three shell scripts and five JavaScript/MJS files; npm root manifest/lock agreement.

PHP and Composer are not available on the inspected shell PATH. No dependency directories are supplied. PHP lint, PHPUnit, Vitest, TypeScript compilation, browser tests, Docker builds and live AI requests were not run. Python AST parsing, `bash -n` and `node --check` did not execute the reviewed source. No vulnerability lookup, provider documentation validation, production inspection, or legal conclusion is included. No new dependency was installed.

## Recommended next step

1. Preserve this bundle/hash and its original history. Use a separate local source checkout initially; resolve README/.gitignore and governance-document integration deliberately instead of merging unrelated roots blindly. This is a recommendation, not an adopted final repository topology.
2. Prepare and approve a disposable local execution configuration with the fake provider and no live keys. Install locked dependencies only after reviewing their installation behavior and authorizing network access.
3. Run existing tests/builds, then add targeted reproductions for the generation/queue failure cases above. Observe one photo workflow with a safe sample and compare its output with agreed concept-drawing criteria.
4. Continue ownership and production handover in parallel. Confirm reuse/asset rights and personal-contact publication permission before sharing the full history. Validate current dependency advisories before any deployment.

Source access now permits discovery to advance. M0's founder vision, showcase observation and shared review remain outstanding; milestone dates, scope, funding and product priorities have not been changed by this inspection.
