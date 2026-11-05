Every client gets a recycle bin: a deleted post goes there and can be restored (decision D20).

## How it works
- **Delete** on a post page, or the bulk **Delete** on the calendar list, moves the post to the client's recycle bin. It leaves the calendar, the grid, the approval links, the reports, the API and the Claude connector, and keeps its captions, media, notes and approval history.
- The new **Recycle bin** tab of each client lists the deleted posts with who deleted them and when. **Restore** puts a post back as it was; **Restore all** does it for the whole bin.
- **Delete for good** removes one post permanently, **Empty the recycle bin** removes all of them. Both ask first.
- Nothing empties the bin on its own.
- Published posts still cannot be deleted. A login limited to some clients sees only their bins.

## Details
- `Post` uses `SoftDeletes` (`posts.deleted_at`, `deleted_by`), `Post::moveToBin()` / `restoreFromBin()`, `RecycleBinController`.
- Post names stay unique: `PostNamer` counts binned posts and takes the highest version plus one. This also fixes a clash that could happen before, when an earlier version of the same name had been deleted.
- Approvals: a deleted post leaves the client's approval link. If that leaves the approval with nothing to decide, nobody is reminded and it closes once overdue; a post restored in time waits in it again, one restored after it closed comes back as a Draft to send again.
- With posting on (`FEATURE_PUBLISHING`): a publish job already queued leaves a binned post alone, and a Scheduled post restored after its time comes back Approved, so a restore never publishes by itself.
- An old link to a binned post (Slack, a mail, the Back button) opens the client's bin instead of a 404.
- The assets page says when a file is used by a post in the bin, so it is not deleted as "unused".
- Greek texts added. No new CSS build: the page uses classes the committed build already has.

## Checks
- Pint passes.
- Full suite: 220 of 221 pass in the build container; the one that does not is the same network-dependent `AutoRepliesTest`, untouched here.
- New `RecycleBinTest` (delete → bin → restore, per-client isolation, delete for good, empty, restore all, unique names, the Scheduled rule, approvals and reminders, the queued publish job, the assets page).
- An independent review pass found the publish-job and approval cases above; both are fixed and tested.
- Looked at in a browser on desktop and phone width, in English and Greek.

## After merging
1. Plesk → Git → **Pull now**.
2. Open https://socialops.inglelandi.com/refresh and press **Refresh after update** (it runs the new migration, which adds two columns to `posts`).

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_01GAFd54RLsKcieieWCSQ4Cx
