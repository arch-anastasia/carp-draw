# CarpDraw — Shared Memory

This journal preserves project context across people, Codex accounts, and chats. [PROJECT.md](PROJECT.md) is the current map; this file explains what was learned or decided and why. [AGENTS.md](AGENTS.md) defines how agents read and maintain both. The same repository revision provides the same recorded context; it cannot supply conversations or evidence that nobody recorded.

## Recording and retrieval

- Before substantive work, read PROJECT.md and this journal, then inspect relevant evidence and handover records. If the journal grows, retain a compact index and search relevant entries; do not introduce a separate memory service.
- Save durable context: accepted decisions, lasting preferences or constraints, material findings, and significant failed experiments, changed assumptions, blockers, or contradictions that affect future work. Save explicitly requested memories. Record proposals only when the user requests it or they are needed to understand continuing project work, and label them Proposed. Skip repeated summaries, acknowledgements, routine status checks, unaccepted daily suggestions, and other responses with no lasting new context. Group related findings and link existing entries; record conclusions and evidence, not a transcript or private reasoning.
- Use stable IDs (`CD-001`, `CD-002`, ...), a date, a kind, and the existing evidence labels: Verified, Reported, Hypothesis, Proposed, Unknown. For a decision, Verified means its stated authority approved it; it does not prove the chosen approach will succeed. Never treat an agent recommendation as human approval.
- Include enough source context here for a new collaborator to understand the record without access to a private chat. Cite safe repository paths, commits, or dated observations where available. For human decisions, identify the role/person who authorized them. Unknown author or rationale stays Unknown.
- For corrections or reversals, add a new entry that names the superseded entry and reason, and update the current map. Preserve relevant history; remove secrets or sensitive information if discovered and coordinate any required history remediation.
- Keep authored records and commit messages in English regardless of conversation language. Preserve exact technical identifiers and evidence where translating them would change meaning. Exclude credentials, private access links, and sensitive user data.
- Save eligible memory locally without asking for separate permission. Batch it with the relevant substantive work in small coherent local commits, rather than committing each conversational answer. If a new sandbox approval would be needed solely for a memory commit, leave the edit local for the next substantive change and disclose that it is uncommitted; honor an explicit commit request. Inspect the diff, preserve unrelated work, and report validation limits or commit blockers. Local commits do not authorize a remote or push. Manual editors should record their reason or provide it when asking Codex to reconcile edits.

For a new entry, use: **date; kind/status; finding or decision; evidence/authority; rationale and meaningful alternatives where relevant; consequences/open follow-up; supersedes, if applicable**. Add another ID only for new context; use Git history for wording-only corrections.

## CD-001

**October 29, 2026 · Finding · Verified — Initial local state**

- **Evidence:** Initial directory listing was empty; `git status` reported no Git repository in the designated CarpDraw workspace. No product source files were available to inspect.
- **Consequence:** Bootstrap began as documentation and organization work. The lack of local source does not establish anything about the external implementation.
- **Follow-up:** Inspect source only when legitimate access arrives; product architecture remains Unknown.

## CD-002

**October 29, 2026 · Finding · Reported — Product and access background**

- **Source:** The technical partner's bootstrap brief reports a working showcase created by the founder, an architect, with AI-assisted tools and another developer's help. Areas of interest include furniture, architectural components, parts, and fittings. Source access is not yet available to the partner; the previous developer's environment may be complex; no project-owned GitHub repository is established.
- **Consequence:** Treat this as reported context, not direct verification of capabilities, rights, completeness, or technical readiness. Frontend/backend structure, providers, data, deployment, account ownership, and operating costs remain Unknown.
- **Follow-up:** Complete the showcase record in [Current Reality](PROJECT.md#current-reality) and the [access record](docs/HANDOVER.md#access-record). Preserve useful progress under full, partial, or no-source access.

## CD-003

**October 29, 2026 · Decision · Verified — Bootstrap boundaries and ownership of decisions**

- **Authority:** The technical partner's explicit bootstrap request authorizes a minimal local Markdown/Git workspace and one review skill. It defers implementation stack and final repository topology until discovery, and prohibits remote creation or pushing without explicit authorization.
- **Rationale:** Shared context and legitimate technical independence can advance before source access. Additional services, application scaffolds, agents, and management systems would add unsupported assumptions and maintenance cost.
- **Responsibilities:** The founder leads vision, creative direction, user understanding, priorities, and daily product evolution. The partner contributes engineering leadership, architecture/security guidance, planning, and investment oversight within agreed constraints. Codex assists; it does not adopt strategic decisions for them.
- **Follow-up:** Review architecture after the audit. Confirm detailed ownership, permissions, and decision boundaries with the founder; these role expectations do not prove legal or account ownership.

## CD-004

**October 29, 2026 · Plan · Proposed — Initial roadmap and economic options**

- **Source:** The bootstrap brief supplied M0 by November 6, 2026; M1 in November; M2 in late November–December; M3 in December; M4 in January–February 2027; M5 in March–April; M6 in May–June, with an open horizon after June. The current scope and completion criteria live in [Roadmap](PROJECT.md#roadmap).
- **Interpretation:** These are starting planning windows, not implementation estimates based on inspected code. M0 is active; the later scope/windows await discussion and revision. Dates alone never establish completion; M0 still needs showcase evidence, the founder's words, and shared review. Access delays must not stop all product discovery.
- **Economic direction:** Accessibility and useful work matter more than revenue maximization. Free, at-cost, patron-supported, and hybrid access are options; none is selected. No budget, prices, funding commitments, or production capacity have been established.
- **Follow-up:** Agree the first useful workflow, audience, acceptance criteria, resources, and scope. Record later approval or changes as new decisions rather than silently treating this proposal as a commitment.

## CD-005

**October 29, 2026 · Finding · Verified — Initial bootstrap and checks**

- **Evidence:** The initial bootstrap created README.md, PROJECT.md, AGENTS.md, docs/HANDOVER.md, .gitignore, and the project-review skill with its `agents/openai.yaml` policy. Git was initialized on `main`; the initial inspection after bootstrap showed no commits or remote. This is a historical snapshot, not the current commit count.
- **Validation:** The initial seven-file inventory and 15 local links passed checks; Mermaid parsed the roadmap with eight nodes and seven connections; the installed Codex skill validator accepted the skill; its YAML policy disabled implicit invocation; ignore-rule checks retained shared files and excluded tested private paths; a common-credential-pattern scan found no matches. These are documentation checks, not product tests or a comprehensive security audit.
- **Implementation choice:** The extra YAML file enforces the explicitly requested review invocation policy. Temporary validation tooling lived outside the repository; no product dependency or application scaffold was added.
- **Follow-up:** Recheck affected documentation as it changes. Inspect the implementation before claiming any technical readiness.

## CD-006

**October 29, 2026 · Decision · Verified — Shared repository memory and local commits**

- **Authority:** The technical partner requested repository-level memory so decisions and findings are logged and collaborators can recover the same context; the same request calls for English logs and commits.
- **Decision:** Add this one journal, require agents to read it with PROJECT.md before substantive work, and record new project findings and decisions before finishing. Commit completed work and associated memory locally with English messages. Maintain the current map and handover details in their existing files; use links to avoid competing histories.
- **Rationale / alternatives:** Git diffs show changed text but often omit intent and unrecorded findings; personal AI history is not a shared project record. One explicit journal preserves evidence and rationale with less complexity than another skill, memory database, or external service.
- **Boundaries:** Logging a proposal does not approve it. Do not commit unrelated work or overwrite manual edits. Separate collaborators share updates only after they receive the relevant commits; remote creation and pushes still need explicit authorization.
- **Follow-up:** Capture the reason for manual plan changes, mark unknown rationale honestly, and supersede outdated conclusions explicitly. Revisit the journal structure only if actual volume makes retrieval difficult.

## CD-007

**October 29, 2026 · Decision · Verified — English records with multilingual conversation**

- **Authority:** The technical partner explicitly requested prompts in any language, replies in that language until the user switches, and English as the repository's base language.
- **Decision:** Match the latest substantive prompt or explicit language instruction for live replies. Retain that language for neutral acknowledgements and code/quoted material; default to English when no preference is available. Keep the preference local to each conversation so collaborators do not change one another's reply language.
- **Consequence:** Write shared documents, findings, decisions, and commit messages in English. Preserve meaning, evidence status, and authorization when translating; do not treat language changes as scope changes. Preserve exact technical identifiers and clarify consequential translation ambiguity.
- **Follow-up:** Apply the rule in AGENTS.md and the review workflow; a duplicate translated documentation tree is unnecessary.

## CD-008

**October 29, 2026 · Finding · Verified — The plan is an editable repository artifact**

- **Evidence:** PROJECT.md contains the current focus, milestone scope and completion criteria, and the Mermaid roadmap as local Markdown. README.md now explains viewing, manual editing, asking for targeted revisions, and reconciling saved edits into memory and Git. The existing dates and milestone scopes have not changed in this update.
- **Clarification:** Roadmap arrows express the intended learning progression; they are not an additional strict scheduling gate. Milestone dependencies determine what can proceed, including overlapping discovery. This clarifies the existing overlapping windows rather than approving a new schedule.
- **Follow-up:** The technical partner wants to discuss scope and timelines next. Capture suggestions as Proposed until authorized; preserve the relevant reasoning when updating the plan.

## CD-009

**October 29, 2026 · Finding · Verified — Shared-memory update validation**

- **Evidence:** After installing the updated review skill, checks passed for the eight-file inventory, all 34 local links/anchors, Markdown whitespace/fence structure, skill frontmatter and explicit-invocation policy, ignore rules, and the absence of common credential patterns. The installed Codex skill validator accepted the skill; Mermaid parsed the unchanged roadmap with eight nodes and seven links. The staged documentation diff was inspected and passed `git diff --cached --check` before the initial commit.
- **Limits / consequence:** These checks establish documentation consistency and syntax, not actual product behavior or a guarantee that every future agent will follow instructions. The history now has explicit read/write guidance and source references that collaborators can review. The dates remain proposed, and M0 remains open.

## CD-010

**October 29, 2026 · Decision · Verified — Management and technical working perspectives**

- **Authority:** The technical partner explicitly requested professional executive summaries and senior operational manager judgment for management topics, and principal software lead judgment for technical topics.
- **Decision:** Select the perspective from the question automatically. Management answers lead with the overall assessment and decision-relevant outcomes, progress, priorities, timelines, ownership, resources, risks, and next actions. Technical answers use system evidence, clear boundaries, failure-mode analysis, explicit trade-offs, maintainability, and meaningful validation. Mixed questions lead with executive implications and supporting technical reasoning.
- **Rationale / consequence:** Match communication and depth to the decision being made without requiring mode commands or adding another agent or skill. Keep human authority, evidence labels, English repository records, and the conversation-language policy unchanged. No milestone, budget, or delivery commitment changes.
- **Follow-up:** Apply this guidance through AGENTS.md; refine it if subsequent feedback warrants a change.

## CD-011

**October 29, 2026 · Plan · Proposed — October 30 priorities**

- **Basis:** The technical partner asked what is planned for tomorrow. PROJECT.md and the handover record identify M0's next actions but contain no confirmed October 30 tasks or meetings; calendar availability was not checked.
- **Proposed outcome:** Founder and technical partner establish a documented starting picture of the showcase and a named next step for authorized handover, supporting M0's November 6 target.
- **Suggested sequence:** Clarify who controls and can authorize code/account access; walk through one showcase task and record inputs, outputs, failures, and manual assistance; capture the founder's intended user/problem and agree the next action with an owner.
- **Dependency / fallback:** Founder availability and permitted showcase access are unconfirmed. If a walkthrough is unavailable, capture the founder's account as Reported when available; independently prepare the focused walkthrough questions and handover request for human review.
- **Status:** This is a recommendation, not an accepted daily commitment or scheduled meeting. No message was sent, no event was created, and no milestone date or scope was changed.

## CD-012

**October 29, 2026 · Decision · Verified — Selective memory and scoped approvals**

- **Authority:** The technical partner requested routine repository edits without repeated approvals, selective memory saving, and approval before activity outside the repo or on the internet. They subsequently authorized inspection of local Codex permission configuration and official OpenAI documentation to prepare repo-only settings; applying a settings change remains a separate approval step.
- **Decision:** Keep durable decisions, preferences, constraints, and material findings in shared memory. Skip routine answers and unaccepted short-lived suggestions. Save eligible records without a separate confirmation, and batch them with related substantive work. Defer a memory-only commit when it would cause a new sandbox approval. Existing scoped authorization covers its necessary steps; external access and sensitive actions still require approval when not already authorized.
- **Rationale / alternatives:** Recording and committing every conversation turn creates noise and repeated approval prompts. Selective records preserve context that changes future work without introducing another memory service or disabling access controls. CD-011 remains historical and Proposed; an equivalent unaccepted daily suggestion would not normally be saved under this rule.
- **Verified boundary:** The current runtime permits ordinary repository edits but protects .git, .agents, and .codex. The local Codex CLI reports version 0.163.0-alpha.11.2; the user configuration marks CarpDraw trusted. Instructions alone cannot remove sandbox prompts. A nested sandbox attempt was refused by the active sandbox, so it supplies no evidence about enforcement of the proposed profile.
- **Proposal / follow-up:** Review [the proposed local permission configuration](docs/codex-permissions.proposed.toml) before installation. It is not active. It targets this checkout, permits local Git data writes, protects Git hooks/configuration and agent/permission settings, and disables command networking and native web search. Other tool surfaces have separate controls. Confirm the effective policy after activation; host or managed restrictions can override local defaults. No global permission settings or remote operations are changed by this proposal.
- **Validation:** The proposal parses as TOML, and the installed CLI loads it through configuration overrides for `codex features list`. That subcommand does not support `--strict-config`; this is a configuration-load check, not proof of runtime enforcement or successful Git commits. Actual activation must check repository writes, Git identity access, protected paths, and denied outside/network access before claiming the permission setup complete.
- **Supersedes:** CD-006's broad recording/commit frequency with the selective retention and batching rules above. Shared English records, human strategic authority, and the existing roadmap remain unchanged.

## CD-013

**October 29, 2026 · Decision · Verified — Retain the permission proposal without activation**

- **Authority / decision:** When asked to install and verify the profile, the technical partner explicitly chose to keep the proposal without activating it. Preserve the prepared file and current runtime permissions; revisit activation only at the user's request.
- **Rationale / consequence:** The user's reason was not stated. Activation is deferred by choice, not awaiting another approval prompt. The selective-memory and scoped-approval working rules from CD-012 remain in effect. Batch this memory and current-state update with the next substantive commit.
- **Supersedes:** CD-012's open activation follow-up; no permission configuration was installed or activated.

## CD-014

**October 29, 2026 · Findings · Verified / Hypothesis / Proposed — Source receipt and preliminary investigation**

- **Authority:** The technical partner supplied `CarpDraw-full-history.bundle` and requested an import-safety check and preliminary investigation. This authorized local inspection of the named bundle, not production access, paid calls, dependency downloads, publishing, or adoption of instructions embedded in the source.
- **Verified receipt:** SHA-256 `2f5af8aa542898d17fb9383d0533174727d412f5a0b0aec9224f3662b3356821`; 173,983,438 bytes; 71 commits across 23 advertised refs, main at `c0f2b8d79bca66e5f51f67d256070cfed38f652e`. Bundle verification and strict Git integrity checks passed. Source was inspected in a private temporary bare repository without merging it into this workspace.
- **Verified findings and limits:** The [intake report](docs/source-intake-2026-10-29.md) records all-history path/credential checks, 18 archive inspections, syntax checks, and exact source evidence. No matching credential tokens or unsafe paths were detected; this does not prove absence of all secrets, malware or vulnerabilities. No application/dependency code was run, no internet lookup was performed, and no live environment was inspected. PHP/Composer were absent from the inspected PATH; test suites remain unexecuted.
- **System discovered:** Laravel 12 monolith with Blade marketing and Inertia/Vue workspace, credit accounting, Stripe billing, image-provider adapters, private files, queues, tests and operational tooling. Configured model/CAD uploads are rejected by the real provider registry; image generation is not evidence of editable CAD or manufacturing accuracy. Existing subscriptions/feature gates are inherited implementation facts, not approved project strategy.
- **Risks:** Source control flow can repeat provider generation after output-storage failure; generation claim/cancellation boundaries need concurrency tests. Post-commit queue failure leaving a charged project without a runnable job is a Hypothesis to reproduce. Docker exposes development services, and deployment depends on missing server-side files. Source/asset rights, account control, actual costs and output quality remain Unknown.
- **Proposed next action / rationale:** A controlled local import with original history preserved is reasonable, followed by an explicitly approved fake-provider reproduction. Keep it isolated initially to avoid overwriting the existing governance workspace or activating inherited workflows; final repository topology is undecided. Obtain sanitized production configuration and ownership evidence in parallel. No milestone window, funding choice, product priority or security permission was changed.
- **Supersedes:** CD-002's report that source is unavailable, and the earlier current-state assumption that no implementation can be inspected. Historical observations remain intact. M0's founder vision, showcase evidence and shared review remain open. The inactive permission decision in CD-013 remains effective.

## CD-015

**October 29, 2026 · Decision and findings · Verified — Root source integration with preserved history**

- **Authority:** After discussing whether to apply the bundle to this root repository, the technical partner explicitly instructed Codex to do so for later GitHub publication. This authorizes local integration, conflict resolution and commits; it does not authorize creating a remote, pushing, installing dependencies or using production services.
- **Decision / implementation:** Merge source main `c0f2b8d79bca66e5f51f67d256070cfed38f652e` into governance main based at `69b1867dec84e98647958dd7f3e17823acef6c68`. Keep the application at the root and preserve both parent histories. Retain all 22 non-HEAD bundle refs under `refs/archive/carpdraw/2026-10-29/heads/` and `refs/archive/carpdraw/2026-10-29/pull/`, keeping all 71 supplied source commits reachable locally. Ordinary main pushes preserve the source-main and governance ancestors, but do not publish the additional archive namespace; retain the original bundle and explicitly plan any publication of side-branch/PR history.
- **Reconciliation:** README.md and .gitignore were the only add/add conflicts. Combine the operating/project documentation with the source's technical reference and ignore rules. Preserve PROJECT.md, MEMORY.md, AGENTS.md and the review skill. Align CLAUDE.md with AGENTS.md rather than treating imported instructions as new authority.
- **Execution boundary:** Move inherited workflows to `.github/workflows-disabled/` and point the workflow-validation script there. They remain available for review but are not discoverable GitHub Actions workflows. No application behavior, dependency version, economic policy or Codex permission profile is changed. No remote, deploy credential or service connection is created.
- **Validation:** Prepared the merge in isolation before applying it. Of 553 source-main files, 549 retain their exact Git blobs and modes, including the two relocated workflows; only README.md, .gitignore, CLAUDE.md and the workflow-check script are reconciled. The merged tree has 561 files and no unresolved entries. Git integrity checks, 58 local Markdown links, 12 ignore-rule cases, workflow YAML parsing and shell syntax passed. The full import whitespace check reports 32,278 pre-existing issues in 28 unchanged reference/asset files; source-relative and edited-governance whitespace checks pass. Original asset bytes were preserved. YAML parsing is not actionlint or runtime validation; dependency installation, application tests and workflows remain unexecuted.
- **Rationale / alternatives:** The initial isolated inspection is complete; the user's root-integration decision now avoids maintaining separate working code/documentation trees. A merge preserves provenance better than copying a snapshot or replacing the existing Git directory. Archival refs retain supplied branch/PR history without merging unselected branch content into main.
- **Follow-up:** Authorize and reproduce a disposable local fake-provider environment, resolve the intake risks, confirm source/asset rights and GitHub owner/visibility, and configure CI before enabling workflows. Runtime tests remain unexecuted. M0 and the roadmap windows remain unchanged.
- **Supersedes:** CD-014's temporary recommendation to keep the application separate pending a topology decision. Its static findings, runtime limitations and publication caveats remain valid; CD-013's inactive permission decision remains in effect.
