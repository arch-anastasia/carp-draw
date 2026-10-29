# CarpDraw — Shared Memory

This journal preserves project context across people, Codex accounts, and chats. [PROJECT.md](PROJECT.md) is the current map; this file explains what was learned or decided and why. [AGENTS.md](AGENTS.md) defines how agents read and maintain both. The same repository revision provides the same recorded context; it cannot supply conversations or evidence that nobody recorded.

## Recording and retrieval

- Before substantive work, read PROJECT.md and this journal, then inspect relevant evidence and handover records. If the journal grows, retain a compact index and search relevant entries; do not introduce a separate memory service.
- Record every new project-relevant finding and decision before finishing, including failed experiments, changed assumptions, blockers, and unresolved contradictions. Group related findings; link existing entries instead of repeating them. Record conclusions and evidence, not a transcript or private reasoning.
- Use stable IDs (`CD-001`, `CD-002`, ...), a date, a kind, and the existing evidence labels: Verified, Reported, Hypothesis, Proposed, Unknown. For a decision, Verified means its stated authority approved it; it does not prove the chosen approach will succeed. Never treat an agent recommendation as human approval.
- Include enough source context here for a new collaborator to understand the record without access to a private chat. Cite safe repository paths, commits, or dated observations where available. For human decisions, identify the role/person who authorized them. Unknown author or rationale stays Unknown.
- For corrections or reversals, add a new entry that names the superseded entry and reason, and update the current map. Preserve relevant history; remove secrets or sensitive information if discovered and coordinate any required history remediation.
- Keep authored records and commit messages in English regardless of conversation language. Preserve exact technical identifiers and evidence where translating them would change meaning. Exclude credentials, private access links, and sensitive user data.
- Commit completed changes and related memory together in small coherent local commits. Inspect the diff, preserve unrelated work, and report incomplete validation or commit blockers. Local commits do not authorize a remote or push. Manual editors should record their reason or provide it when asking Codex to reconcile edits.

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
