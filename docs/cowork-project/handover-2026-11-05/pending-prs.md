# Social Ops: link in bio removed (merged) and recycle bin (PR open)

Written 2026-11-05 16:20 (Athens), updated the same afternoon. Requested in the project chat: "delete the link in bio completely" and "each client will have its own recycle bin".

**The repository is `inglelandi/SocialOps`** (https://github.com/inglelandi/SocialOps), not `Kokouliss/SocialOps`.

## Where things stand

- **PR 1, link in bio removed (D19): merged** as #123 (https://github.com/inglelandi/SocialOps/pull/123), `main` at `a977d72`.
- **PR 2, recycle bin (D20): open as #124** (https://github.com/inglelandi/SocialOps/pull/124), branch `claude/link-in-bio-recycle-bin`, commit `dec0a86` on top of `a977d72`, assigned to Panos. Its tree matches `claude/pr2-recycle-bin.patch` exactly (26 files, +456 −36, every blob hash equal to the patch's). Pint passes; GitHub's test run on the PR is green; in the cloud container 220 of 221 (only `AutoRepliesTest`, see below).
- Another thread has #122 open ("save-plan accepts a plan without pillars": `SavePlan.php`, `PlanWriter.php`, `McpPlanningTest.php`). It shares no file with #124.

## What is left to do

1. After #123 is deployed: Plesk "Pull now", then /refresh (it has a migration). Same again after #124.
2. After #124 is merged, delete the four `claude/pr*` docs and this one.

## Things the next chat should know

- Before PR 1 is deployed: if any client's Instagram or TikTok profile points to `socialops.inglelandi.com/bio/…`, that link will 404. A `links` page already published on a client's WordPress site stays there until deleted in WordPress.
- `AutoRepliesTest` fails in the cloud container and passes on GitHub's runner: its `Http::fake` does not cover `/{id}/conversations`, so it calls the real graph.facebook.com, which the container's proxy refuses (403). A one-line fake would make the suite independent of the network; not done because it is a separate item.
- A project doc holding a patch may come back inline instead of as a local file; then apply its changes by script and prove the result with the blob hashes on the patch's `index` lines (`git rev-parse --short=7 :path`) and the stat line.
- GitHub from the container: `gh pr …` does not work (GraphQL is blocked); use REST, `gh api repos/inglelandi/SocialOps/pulls …` and `…/issues/<n>/assignees`.
- In the cloud container `composer install` cannot download package archives (they are blocked), so it falls back to cloning every package: allow about 20 minutes and do not interrupt it. `ReelTest` needs `ffprobe` next to `ffmpeg` (`ln -s /usr/bin/ffprobe /usr/local/bin/ffprobe`).
- Neither PR needed a new `public/build`. Note for later view changes: `tailwind.config.js` also scans `storage/framework/views/*.php`, so a rebuild's output depends on which compiled views happen to be on the machine (the committed CSS carries classes from Laravel's own error pages). Harmless, but a rebuild shows a diff even when nothing changed.
- A user-configured gate (GateGuard) asks for a short statement of the request and of what a command or file is for before the first Bash command, before creating a file and before destructive commands; state the facts and retry.
