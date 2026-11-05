# CarpDraw — Ownership and Technical Handover

Baseline: October 29, 2026. A user-supplied source bundle has been inspected offline; see the [source intake report](source-intake-2026-10-29.md). The showcase, actual services and ownership remain unverified. Use this checklist to recover understanding and legitimate control, even when access is limited.

## How to use this checklist

Start with the founder and the current custodian. Agree what may be inspected, copied, executed, shared, or transferred. Read-only inspection does not authorize live operations, paid inference, changing access, or moving data. Review supplied scripts/configuration before executing them.

For each item, record **status, responsible person, dated evidence reference, gap, and next action**. Use Verified / Reported / Hypothesis / Proposed / Unknown as defined in PROJECT.md. Check an item only when evidence supports it; mark not applicable only with a reason. A verbal claim can be recorded as Reported without checking the item.

Keep credentials, tokens, recovery codes, private access URLs, and sensitive data in an approved secure channel or store, never here. Record only safe references to their custodian/location. Do not circumvent access restrictions or assume lack of access grants permission to recreate proprietary material.

## Access record

Use one row per actual component/account as it becomes known; these rows are discovery areas, not an assumed system architecture. Record a named owner or explicitly keep Unknown.

| Area | Access / verification status | Owner / authorizer | Evidence reference and date | Gap / next action |
| --- | --- | --- | --- | --- |
| Source and history | Verified receipt, static inspection and local fake-provider runtime | Technical partner supplied the bundle; upstream custodian/rights remain Unknown | [October 29 source intake](source-intake-2026-10-29.md), main `c0f2b8d`, 71 commits across included refs | Root integration and local reproduction are complete; confirm provenance/rights and collect live-service evidence. See [CD-015](../MEMORY.md#cd-015) and [CD-016](../MEMORY.md#cd-016). |
| Repository collaboration | First main push and remote-tip match verified; other collaborator access Unknown | Technical partner configured the destination and authorized publication | Git push/readback on November 5, 2026; [CD-020](../MEMORY.md#cd-020) | Verify intended visibility, permissions and another developer’s clone; archive refs remain local. |
| Showcase | Reported to exist; observation pending | Unknown; ask founder | Same brief; no walkthrough yet | Arrange permitted observation and clarify what can be recorded. |
| Services and deployment | Source configuration inspected; live setup/access Unknown | Unknown | Deployment workflow depends on an absent server-side script; source docs describe separate production Compose/Nginx | Obtain sanitized server configuration, runner/protection details, rollback instructions and account/billing owners. |
| Data and assets | Unknown | Unknown | None | Establish provenance, access rules, and permitted samples. |

Current next contact: **Unknown — founder and technical partner to agree**. Current accessible scenario: **Scenario B — Partial Access**: the supplied application source/history is available for inspection, and a local SQLite/fake-provider environment runs, while production/account configuration and live-provider evidence remain missing. A description relayed by the founder is Reported until observed or otherwise directly supported.

### Source intake record — October 29, 2026

Git integrity, all-history path checks, bounded credential/archive checks and non-executing syntax checks passed. The [report](source-intake-2026-10-29.md) records the bundle hash, coverage, source structure and risks. No application code, install, container, migration or workflow was run. The subsequent authorized root import preserves original source history and this repository's operating rules. Inherited workflows are inactive templates under `.github/workflows-disabled/`; enabling them requires a reviewed CI/deployment configuration. The later scoped localhost request and installation approvals enabled the reproduction below; the intake itself authorized no execution.

The source contains 3D/CAD workflow claims beyond the real image providers' accepted formats, retry/cancellation risks, and inherited subscription policy. Keep these visible during the walkthrough; source presence does not demonstrate functionality, rights, accepted product scope, or production control.

### Local reproduction record — October 29, 2026

**Verified:** The technical partner requested localhost development for testing ongoing changes. Scoped tool approvals covered Homebrew PHP, Composer/npm dependency downloads and loopback listeners. Native PHP 8.4.26, Composer 2.11.0, Node 24.15.0 and npm 11.12.1 run the existing lockfiles with SQLite, a database queue, log mail and the fake provider. Local data and configuration are ignored. The [README](../README.md#local-development) records setup, startup, shutdown, PHP upload-limit configuration, commands and validation limits.

The build, 270 PHP tests, 161 Vitest tests and PHPStan passed. A browser smoke run exercised sign-in, a bundled photo upload, database-queue completion and a saved sample output; credits moved from 12 to 8, with zero pending/failed jobs. Both listeners bind to loopback, and HMR reacted to a file change. No real inference, production service, remote publication or deployment was tested. Full Playwright, PostgreSQL/Redis, backup/restore and a second-developer reproduction remain outstanding. See [CD-016](../MEMORY.md#cd-016).

## Ownership and permissions

- [ ] Identify who owns source code, repository history, designs, generated outputs, assets, and data; retain safe references to supporting agreements.
- [ ] Clarify contributions from the previous developer and any third parties, intellectual-property terms, and permission to modify, operate, transfer, and redistribute each relevant component.
- [ ] Inventory software, model, dataset, font, and asset licenses and applicable provider terms; identify restrictions and unresolved permissions.
- [ ] Record account owners, authorized administrators, billing responsibility, recovery contacts, and current access permissions. Agree a project-controlled ownership destination before transfers.
- [ ] Agree handover scope, contact, expected delivery, unavailable material, and how missing access will be resolved. Preserve provenance/history when available; do not overwrite originals.

## Frontend

- [ ] Locate application entry points and structure; identify language, framework, dependency manifests, exact versions/lockfiles, build tooling, and required runtime/tool versions.
- [ ] List runtime/build configuration names, purpose, and source of values, with redacted or synthetic examples only. Identify anything exposed to clients.
- [ ] Trace observed user actions to external integrations and backend interfaces, including authentication/session behavior, errors, retries, and manual interventions.
- [ ] Inventory assets, their source/license, generation process, storage, and missing resources.
- [ ] Identify hosting, deployment steps, environment-specific configuration, domain relationship, release owner, and rollback path.

## Backend

- [ ] Locate application structure, language/framework, dependency versions, entry points, and runtime requirements. Confirm whether it is separate from or coupled to the frontend.
- [ ] Describe API interfaces and frontend contracts as implemented, including validation, errors, compatibility, and request limits; do not assume a protocol.
- [ ] Trace authentication and authorization boundaries, identities/roles, service credentials, and permitted data access.
- [ ] Identify persistence, schemas/data flows, ownership, migrations, consistency/concurrency assumptions, retention/deletion, and recovery paths.
- [ ] Inventory background processing, scheduling, queues if present, timeouts/cancellation, retry bounds, duplicate processing, and idempotency of paid or state-changing operations.
- [ ] Trace AI integrations: providers/models/versions, execution location, prompts/input preparation, output handling, quotas, data retention, failures, and attribution of charges.
- [ ] Record runtime configuration names and custody, hosting, deploy/build commands, environments, manual steps, operational owner, and rollback limitations.

## Infrastructure

- [ ] Inventory domains, DNS zones/registrars, renewal owners, cloud accounts, hosting services, and other external-service dependencies.
- [ ] Identify secrets storage, delivery to runtime/CI, authorized custodians, recovery access, and gaps; never copy secret values into documentation.
- [ ] Map deployment workflows, existing CI/CD, identities/permissions, environment separation, approvals, and manual operations.
- [ ] Locate logs, error reporting, metrics/monitoring, alerts, retention, access permissions, and who responds. Check whether sensitive inputs or outputs are captured.
- [ ] Identify backups, covered data/assets/configuration, retention, restore instructions, and evidence of recovery. If a restore has never been tested, say so.
- [ ] Identify provider costs, billing owners, recurring renewals, usage quotas, spending limits, and the response when a budget is exhausted.

## Reproducibility

- [ ] Record dependency and tooling versions, supported local environment, acquisition sources, and all required assets/configuration.
- [x] Recover local setup instructions and environment-variable names with explanations and secret-free examples; mark external dependencies that prevent standalone operation. Verified for the SQLite/fake-provider profile, October 29; see README. Live integration configuration remains open.
- [x] Record actual build, test, and run commands, expected results, known failures, and any migrations or seed data. Verified for the local profile, October 29; see the reproduction record and README. Production and billable operations remain separate.
- [ ] Identify undocumented manual steps and operator-only knowledge. Ask another authorized developer to repeat the setup in an isolated environment; record date, version/commit, result, and remaining gaps.

## Security

- [ ] Review credential exposure and custody. Agree a rotation/revocation sequence with service owners, update consumers, verify access, and preserve recovery before removing old access. Approval is required for the actual changes.
- [ ] Establish separate named accounts and least privilege; review shared accounts, privileged automation, and external-service ownership/recovery paths.
- [ ] Identify data categories, input/output ownership and consent, service/provider access, retention/deletion obligations, and suitable sanitized discovery examples.
- [ ] Check licenses and third-party obligations against intended use, including assets and model outputs where relevant. Record unresolved rights for qualified review rather than making legal assumptions.
- [ ] Review material and history for secrets before importing or sharing. If credentials are discovered, report their location without reproducing values, restrict further sharing, and coordinate remediation with the owner.

## Access scenarios

### Scenario A — Full Access

**Entry:** Authorized access to all relevant source and configuration; confirm separately whether production operations or data access are permitted.

1. Inventory source/history, components, services, owners, and configuration names; review scripts before execution.
2. Reproduce build/test/run in an isolated environment with approved data and bounded provider usage. Trace one workflow across frontend, backend, inference, storage, and deployment.
3. Record observed dependencies, manual steps, failures, cost evidence, and recovery gaps in PROJECT.md. Coordinate account transitions and credential rotation only with approval.

**Useful outcome:** A reproducible, evidenced description of the current system, plus explicit remaining risks and a justified next experiment. Access alone is not proof of readiness.

### Scenario B — Partial Access

**Entry:** Some source, configuration, or services remain inaccessible; record the exact authorized boundary.

1. Inventory accessible components and list each missing dependency, its custodian, and its impact. Mark whether local execution, a useful workflow, deployment, or ownership is blocked.
2. Ask the authorized owner for the smallest safe missing artifact or interface description. Observe permitted behavior; label any test substitutes as Hypothesis and do not treat them as recovered implementation.
3. Reproduce only what access supports; continue user/problem discovery and alternative comparison. Separate accessible-system evidence from unresolved claims.

**Useful outcome:** A bounded technical inventory, access-gap register, and useful experiment that can proceed now. Retain, replace, or independent reconstruction remains a human decision based on rights, cost, and evidence.

### Scenario C — No Source Access

**Entry:** Only permitted showcase observation is available. If even that is unavailable, begin with the founder's account and label it Reported.

1. Document a real task: intended user, input, visible steps, output, timing, failures, required manual assistance, and whether the result can be used downstream. Retain screenshots/sample outputs only with permission and safe handling.
2. Ask prospective users how they solve that task now; compare alternatives and define what a useful result would require. Do not infer hidden architecture or true operating cost from a screen recording.
3. Clarify ownership/access expectations and remaining dependencies. Prepare an independent experiment proposal from legitimate requirements and available resources; obtain human agreement before committing to a rebuild.

**Useful outcome:** An observable product description, candidate user problem, acceptance questions, and proposed M1 scope that does not wait entirely on source delivery. Technical independence and production readiness remain unverified.

## First handover conversation

Agree who can authorize access and what can be shared; arrange one showcase walkthrough; request the smallest available source/configuration/service inventory; assign an owner and next action to each missing item. Return the consequential findings to [Current Focus](../PROJECT.md#current-focus) and [Technical Readiness](../PROJECT.md#technical-readiness). Keep this checklist as the collection record rather than duplicating the roadmap.
