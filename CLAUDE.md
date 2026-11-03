# Social Ops: in-house social media system

## What this is
An internal tool for our agency (Chania, Crete) to plan, create, approve and publish social media posts for **many client accounts**, faster. Clients are mostly hospitality: villas, hotels, beach bars/lounges, restaurants, local brands. Content is **Greek and English**.

Goal: cut time from every step: asset intake, captions, translation, approvals, scheduling, reporting.

## Status
- Research done (November 2026): `docs/research.md`. Read it before proposing architecture or features.
- Scaffold, clients CRUD, asset inbox, posts and calendar, approval portal, AI drafting, publishing drivers done. Next: the first unticked Phase 1 item in `docs/roadmap.md`.
- Decisions taken so far and decisions still open: `docs/decisions.md`. **Ask the user about open decisions before writing code that depends on them.**

## Core idea
Own the workflow and the publishing. No paid middleman.
- We build: client/brand profiles, asset inbox, content calendar, AI bilingual captions, client approval portal, reporting.
- We build the publishing too: our own Meta developer app (Instagram + Facebook via the Graph API), TikTok Content Posting API, Google Business Profile API, each a driver behind a `Publisher` adapter interface. No paid aggregator (Panos's call, D2).
- Consequence: platform approvals (Meta App Review, TikTok audit, Google Business Profile access) are the critical path. Start Phase 0 today. Build and test every driver in dev mode with the agency's own test accounts while approvals run; client publishing goes live per platform as each approval lands.

## MVP scope (Phase 1)
1. Client & brand profiles (accounts, voice, languages, pillars, hashtag bank, approval policy, approver contacts).
2. Asset inbox per client: no-login upload link, auto-naming, tags.
3. Content calendar with post state machine.
4. AI drafting: Claude API, EL + EN captions, per-platform variants, alt text, hashtags.
5. Client approval portal: signed expiring magic links, per-network previews, inline comments, approve on mobile, reminders.
6. Publishing to Instagram, Facebook, TikTok and Google Business Profile through our own drivers. TikTok posts stay private (SELF_ONLY) until the audit passes; until then public TikTok posts go through Manual-Needed. No other platforms, now or later.
7. Token health monitor, plus `posts:preflight` (hourly: every account with a post due in the next 24 hours answers one cheap API call, or Slack hears about it once a day) and `posts:reconcile` (hourly: Google Business Profile posts of the last 24 hours are read back; a REJECTED one loses its id, gets the reason on the post and a Slack line).

Out of MVP: CSV import, evergreen queues, Canva autofill, auto-reports, auto-Reels, link-in-bio. LinkedIn, YouTube, Threads, Pinterest and X are not planned.

## Post state machine
`Idea → Draft → In Review → Changes Requested → Approved (locked) → Scheduled → Published | Failed | Manual-Needed`, and `Published → Withdrawn` (the Withdraw button deletes the post through the Facebook and Google Business APIs; Instagram and TikTok have none, so those stay listed on the post with a note until a human deletes them in the app).
- Approved posts are locked; editing one sends it back to In Review.
- Approval auto-schedules the post.
- A post with `settings.repeat` (weekly or monthly) spawns its next occurrence as a Draft right after it is published (`Post::spawnRepeat()`); the Draft goes through approval again.
- Code: `App\Posts\PostState` (enum with the transitions), `Post::transitionTo()`; captions live in `post_variants` per platform and language; `Post::captionFor()` builds the published text (languages, then the profile's `signatures.<platform>` line, then the hashtags).
- Numbers: `posts:insights` (daily) reads reach, views, likes, comments, shares and saves per platform into `posts.metrics` from Instagram, Facebook and TikTok (Display API, scope `video.list`); the monthly report adds the Google Business Profile month live (Performance API: views on Search and Maps, website clicks, calls, direction requests; per location, not per post).
- Manual-Needed covers things no API can do (Instagram Story stickers, shopping tags, public TikTok through our own app before its audit): notify a human to post in-app.

## Rules that must not be broken
- Never store client passwords. Official OAuth only.
- Never hard-code platform quotas. Read live limits (for example Instagram `content_publishing_limit`) before batch publishing.
- A human stays in the approval gate. AI drafts; it never publishes unapproved content.
- Greek captions get human review (gender agreement, idioms).
- Client data and media in EU-region storage. Log UGC repost permission and consent for people in assets.
- Each client is isolated: accounts, media, users, data.

## Dev setup
`composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && npm install && npm run build`. The first staff login is created on `/setup` (only while there are no users) or with `php artisan staff:create "Name" email`; more logins on the Staff page (no public registration). A login can be limited to some clients there (`users.client_ids`, enforced by `App\Http\Middleware\LimitClients` on every staff page and in the lists): it sees only those clients and cannot manage logins. Run `php artisan test` and `vendor/bin/pint --test` before every commit; CI runs both. Reminders and escalations need the scheduler (`php artisan schedule:work` locally, cron in production).

## Conventions
- Asset/post naming: `CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#`, e.g. `ALMYRA_20270512_IG_REEL_FOOD_EL-EN_v2`. Enforced automatically on upload; raw inbox uploads use `ANY` for the platform and `IMG`/`VID` for the format (e.g. `ALMYRA_20261103_ANY_IMG_FOOD_EL-EN_v1.jpg`) because platform and post format are decided later on the post.
- Instagram grid preview per client (`/clients/{id}/grid`): feed, carousel and Reel posts with a date, newest first, first asset as a thumbnail (`/assets/{id}/thumb`, `Asset::preview(600)`).
- Client brand profiles live in the `clients` table (`profile` JSON column in the `example.yaml` shape, `Client::toProfileArray()` returns the full shape). `config/clients/<slug>.yaml` files are imported with `php artisan clients:import`; `example.yaml` stays the template. Every client-owned table carries `client_id`.
- Placeholders: the profile's `fields` map (`{{phone}}`, `{{site}}`, `{{booking}}`, any lowercase name) is filled by `Client::substitute()` in captions, signatures and the first comment at publish time, in the approval preview, the live counter, the ZIP and the CSV; an unknown placeholder fails the publish instead of going out as written.
- Posts the client published themselves in the Instagram or Facebook app: the "Import last 30 days" button on the accounts page (`App\Posts\PostImporter`) pulls them in as Published posts with their external id, so the calendar, the insights and the monthly report see them; running it again imports only what is new.
- Clients connect their own accounts through the signed "connect link" on the accounts page (`/connect/{slug}`, 7 days): same OAuth flow and pick step as staff, in the guest layout, `connected_by` empty. The OAuth callback and the pick step are outside the auth group on purpose: the state and candidates in the session are the credential.
- View-only client calendar: signed link from the staff calendar (client selected), `/calendar/{slug}`, 30 days, this month and the next, states in client words (`CalendarController::CLIENT_STATES`).
- Caption prompt: `prompts/caption-draft.md`. Shortening prompt: `prompts/shorten-caption.md` (`App\Drafting\CaptionShortener`, the "Shorten with Claude" button next to each platform's live character count on the post form). Comment reply prompt: `prompts/comment-reply.md` (comment inbox per client, `php artisan comments:fetch` hourly: Instagram and Facebook comments plus Google Business Profile reviews, which share the `comments` table with a `rating`). Triage prompt: `prompts/comment-triage.md` (`App\Engagement\CommentTriage` tags each new comment with a sentiment and an intent at fetch time; `Comment::needsAttention()` sorts the inbox and counts into the Slack line; `saved_replies` in the profile are offered next to the reply box).
- Instagram collab posts: `settings.instagram.collaborators` on the post form (up to 3 usernames, feed, carousel and Reel) goes on the media container as `collaborators`; the invited accounts accept in the app. Not for Stories.
- Team notes on a post (`post_notes`, `PostNote`, the box under the post form, also on published posts): internal only; `@FirstName` in a note pings that colleague on Slack with a link (`PostNote::mentioned()`).
- Listening report prompt: `prompts/listening-report.md` (`App\Reporting\ListeningReport`, weekly `listening:report`: the profile's `research.hashtags_to_watch` through the Instagram Hashtag Search API, `research.competitors` through Business Discovery, both via the client's connected Instagram account; no scraping, ever).
- Keyword auto-replies: a `saved_replies` entry with `keywords` answers a new Instagram or Facebook comment that contains one of them at fetch time (`FetchComments::autoReply()`, the saved text as written by a human, marked replied); comments the triage flagged (question, complaint, negative) or could not tag wait for a human, Google reviews always do.
- Monthly report prompt: `prompts/monthly-report.md` (`App\Reporting\MonthlyReport`: numbers built in PHP from `posts.metrics`, read back daily by `posts:insights`; Claude writes the reading).
- UTM: `utm: true` in the profile makes `Client::tagLinks()` add `utm_source=<platform>&utm_medium=social&utm_campaign=<post name>` to every link in captions, first comments and Google buttons at publish time; links that already carry `utm_` are left alone.
- Trend brief and batch plan prompts: `prompts/trend-research.md`, `prompts/content-plan.md`. `php artisan content:plan <client> --posts=N` drafts a reviewable batch; `--brief <file>` skips the web research and uses a pasted brief instead. The "Plan with Claude" form on the client page runs the same command as a queued job (`App\Jobs\PlanContentBatch`) and mails the outcome.
- Monthly report to the client: "Email to client" on the report page, or `approval.email_monthly_report: true` for the job to do it, mails the approvers a signed 30-day link (`/reports/{id}/client`, guest layout) with the month's numbers attached as CSV (`MonthlyReport::mail()`, `MonthlyReport::csv()`).

## Files
- `docs/research.md`: full research report with sources (tools, APIs, workflow, AI, architecture, risks).
- `docs/roadmap.md`: phases with checklists. Tick items as they finish.
- `docs/decisions.md`: decision log.
- `config/clients/example.yaml`: brand profile template.
- `prompts/caption-draft.md`: caption drafting prompt.
- `docs/pilot-setup.md`: one-time setup of the Meta, TikTok and Google apps, the server and the first client.
- `.claude/commands/`: `/kickoff` (first session), `/next` (continue the roadmap).
