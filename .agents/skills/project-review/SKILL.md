---
name: project-review
description: Review CarpDraw progress against PROJECT.md when the user explicitly requests a project review or invokes $project-review. Identify evidence, blockers, contradictions, and a small next step, and propose project-map updates. Do not invoke for ordinary implementation work or routine edits.
---

# CarpDraw Project Review

Resolve the repository root from this skill's location or Git, and follow its AGENTS.md. Review only when explicitly requested. Do not schedule reviews or create other skills or subagents.

1. Read the root [PROJECT.md](../../../PROJECT.md), especially Current Focus, the active milestone's evidence criteria, and Recent Decisions. Read [MEMORY.md](../../../MEMORY.md) and relevant prior entries to recover decision authority, rationale, and superseded findings.
2. Inspect available working-tree changes, relevant history if any exists, and the evidence bearing on the milestone. Include staged, unstaged, and relevant untracked files; an empty Git history is not an error. Consult [HANDOVER.md](../../../docs/HANDOVER.md) for access and ownership gaps. Do not run paid, privileged, or external operations to conduct a review without authorization.
3. Compare observed outcomes with the active milestone. Distinguish completed, evidenced work from activity, reported claims, and missing evidence. Do not infer product readiness from documentation or a demo, or milestone completion from a date.
4. Identify consequential contradictions, blockers, hidden dependencies, and relevant trade-offs across product, frontend/backend boundaries, AI behavior, data, operations, ownership, and cost. Keep missing access explicit; suggest productive work within the authorized boundary.
5. Recommend the smallest useful next action and how its outcome can be observed. Reflect on what was created, learned, unsuccessful, and what matters next without turning the review into a large backlog.
6. Apply MEMORY.md's retention criteria: save durable decisions and material findings, and proposals only when explicitly requested or needed to understand continuing work. Include safe evidence references, dates, rationale, and unresolved questions. If the review adds no durable context, leave memory unchanged; no separate permission is needed for an eligible memory save. Apply factual updates to PROJECT.md within existing authorization and link the memory entries; propose strategic changes without adopting them. Use Verified, Reported, Hypothesis, Proposed, and Unknown consistently. Preserve manual edits and record unknown rationale honestly.
7. Obtain human confirmation before applying strategic changes to vision, audience, priorities, milestone scope/windows, ownership, funding, or major architecture. First show the concrete proposal, meaningful alternatives, trade-offs, and affected text. Existing explicit authorization remains valid; a request to review alone is not strategic approval.

Return a concise review in the user's current conversation language with milestone assessment, supporting evidence and gaps, the most consequential blockers or contradictions, one next action, recorded findings, proposed document edits, and any decisions requiring people. Repository records and commit messages stay in English. Follow AGENTS.md for local commits of completed authorized changes. Never record a milestone as complete without its stated completion evidence. Preserve the founder's creative ownership and avoid sensitive data in the review or repository.
