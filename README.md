# CarpDraw

CarpDraw is an early exploration of AI-assisted creation and work with digital models for furniture, architectural components, parts, and fittings. The intended users, useful workflows, and output requirements are still being discovered.

A working showcase has been reported. On October 29, 2026, a supplied source bundle was inspected offline: it contains a Laravel/Vue application with queued image generation, billing and operational tooling. See the [source intake report](docs/source-intake-2026-10-29.md) for verified findings and gaps. The code has not been merged into this documentation workspace or run here; showcase behavior and production readiness remain unverified.

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

New work starts from PROJECT.md and MEMORY.md. Codex saves durable decisions, constraints, and material findings, then batches memory with related substantive work in local commits. Routine answers and unaccepted daily suggestions do not need a journal entry or commit. Eligible memory saves need no separate confirmation; a memory-only commit can wait if it would trigger sandbox approval. Personal chats are supplementary. Collaborators share recorded context when they use the same repository revision. No remote synchronization exists yet. Build/test commands exist in the received source, but installation and execution have not been verified or authorized by this intake review.

Routine reversible repository work is authorized within the requested task. Outside-repo access, internet use, and sensitive or externally visible actions require scoped approval unless already authorized. Codex's actual sandbox can still protect Git and agent settings; repository instructions do not override it. The [proposed permission configuration](docs/codex-permissions.proposed.toml) is available to review but is not installed. Creating a remote or pushing requires explicit authorization.
