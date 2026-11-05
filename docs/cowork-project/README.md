# The Cowork project "Social Ops", moved here

Until 2026-11-05 the Social Ops threads ran in a Claude Cowork project called "Social Ops". On 2026-11-05 everything it held was exported read-only (the project's own instructions, docs and memory) and filed in this folder, so Claude Code sessions on this repository have it.

This folder is background. The code, `CLAUDE.md` and the rest of `docs/` are the source of truth; where a file here disagrees with them, they win.

## What the project held

| File | What it is | Written (UTC) |
|---|---|---|
| `instructions.md` | The project's custom instructions, verbatim. The same three rules are now in `CLAUDE.md` under "Working rules". | |
| `claude-worker-prompt.md` | The prompt for the hourly worker: a scheduled task that runs the jobs the team queues for Claude in the app (`list-claude-jobs`, then the caption and planning recipes). | 2026-11-05 13:17 |
| `social-ops-handoff.md` | The handoff written when the project moved to a new Claude account: the project instructions, the old account's memory files and the PR history up to #121. | 2026-11-05 13:08 |
| `memory/social-ops.md` | Project memory: what the app is, the 2026-11-04 pivot, status, decisions, hard rules and the thread workflow. | 2026-11-05 13:53 |
| `memory/social-ops-hosting.md` | Project memory: the server, the deploy routine after a merge, Plesk and LiteSpeed quirks, mail and Passport fixes. | 2026-11-05 13:16 |
| `memory/social-ops-claude-connector.md` | Project memory: the OAuth MCP connector and its tools, the Claude job queue and the hourly "Social Ops: queued Claude jobs" scheduled task. | 2026-11-05 13:16 |
| `memory/social-ops-pr-history.md` | Project memory: what PRs #1 to #121 added, and the coding traps hit. | 2026-11-05 13:16 |
| `memory/panos.md` | Project memory: how Panos works and how to hand him server steps. | 2026-11-05 13:16 |
| `handover-2026-11-05/` | The last handover notes (`pending-prs.md`, the two PR texts and the two patches), saved before the chat that wrote them could push. Already deleted from the project when this was exported; recovered from that chat's history. | 2026-11-05 14:52 |

The project had no uploaded files and no synced sources.

## How current it is

- The memory files and the handoff predate #122 to #124. They say "#1 to #121 merged, none open" and describe link in bio on the client's WordPress (D15) as live. Since then:
  - #123 removed link in bio completely (D19).
  - #124 added the recycle bin per client (D20).
  - #122 ("save-plan accepts a plan without pillars") was still open at export time.
- The handoff names the old repository `Kokouliss/SocialOps`; the work now happens in `inglelandi/SocialOps`.
- The two patches in `handover-2026-11-05/` are what was merged: `pr1-remove-link-in-bio.patch` matches commit `2ec966e` (#123) and `pr2-recycle-bin.patch` matches `dec0a86` (#124), file for file by the blob hashes on their `index` lines. Kept as a record only; do not apply them again.
- At export time no scheduled task for the hourly worker in `claude-worker-prompt.md` was listed on this account (tasks the Cowork desktop app keeps locally are not visible from a cloud session).

## Redactions

This repository is public, so two values were replaced before commit, the way `docs/pilot-setup.md` writes them:
- `SERVER-IP`: the server's real IP address, which sits behind Cloudflare.
- `SYSTEM-USER`: the server's system user for SSH.

The real values are in Plesk and in the memory of the original Cowork project.
