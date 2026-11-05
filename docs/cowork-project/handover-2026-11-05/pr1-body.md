Removes link in bio from Social Ops completely (decision D19).

## What goes
- The public page `/bio/<client>`: it now answers 404.
- The "Link in bio" box on the client's Profile page, together with the WordPress connection of D15.
- `App\Bio` (`BioLinks`, `WordPress`), `BioController`, `WordPressController`, their routes, views, tests and Greek texts.
- The stored WordPress application passwords: a migration deletes the `accounts` rows of platform `wordpress`.

## What does not change by itself
- If a client's Instagram or TikTok profile points to `socialops.inglelandi.com/bio/…`, that link stops working after the deploy: put the client's own site there first.
- A `links` page already published on a client's WordPress site stays on that site. Delete it in WordPress if it should go, and revoke the application password made for Social Ops (WordPress → Users → Profile → Application Passwords).
- The words "link in bio" in the caption call-to-action examples stay: that is caption wording, not this feature.

## Checks
- Pint passes.
- Full suite: 211 of 212 pass in the build container. The one that does not is `AutoRepliesTest`, which calls the real Facebook API (`/conversations` is not faked) and has no network there; this change does not touch it and it passes on GitHub's runner.
- New `LinkInBioRemovedTest`: the page is 404, the Profile page has no box, the migration deletes only the WordPress rows.
- No new CSS build: the removed pages used their own inline styles.

## After merging
1. Plesk → Git → **Pull now**.
2. Open https://socialops.inglelandi.com/refresh and press **Refresh after update** (it runs the new migration).

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_01GAFd54RLsKcieieWCSQ4Cx
