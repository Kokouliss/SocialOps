# Social Ops: in-house social media system

## What this is
An internal tool for our agency (Chania, Crete) to plan, create, approve and publish social media posts for **many client accounts**, faster. Clients are mostly hospitality: villas, hotels, beach bars/lounges, restaurants, local brands. Content is **Greek and English**.

Goal: cut time from every step: asset intake, captions, translation, approvals, scheduling, reporting.

## Status
- Research done (November 2026): `docs/research.md`. Read it before proposing architecture or features.
- Scaffold, clients CRUD, asset inbox, posts and calendar, approval portal done. Next: the first unticked Phase 1 item in `docs/roadmap.md`.
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
7. Token health monitor.

Out of MVP: CSV import, evergreen queues, Canva autofill, auto-reports, auto-Reels, link-in-bio. LinkedIn, YouTube, Threads, Pinterest and X are not planned.

## Post state machine
`Idea → Draft → In Review → Changes Requested → Approved (locked) → Scheduled → Published | Failed | Manual-Needed`
- Approved posts are locked; editing one sends it back to In Review.
- Approval auto-schedules the post.
- Code: `App\Posts\PostState` (enum with the transitions), `Post::transitionTo()`; captions live in `post_variants` per platform and language.
- Manual-Needed covers things no API can do (Instagram Story stickers, shopping tags, public TikTok through our own app before its audit): notify a human to post in-app.

## Rules that must not be broken
- Never store client passwords. Official OAuth only.
- Never hard-code platform quotas. Read live limits (for example Instagram `content_publishing_limit`) before batch publishing.
- A human stays in the approval gate. AI drafts; it never publishes unapproved content.
- Greek captions get human review (gender agreement, idioms).
- Client data and media in EU-region storage. Log UGC repost permission and consent for people in assets.
- Each client is isolated: accounts, media, users, data.

## Dev setup
`composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && npm install && npm run build`. Create a staff login with `php artisan staff:create "Name" email` (no public registration). Run `php artisan test` and `vendor/bin/pint --test` before every commit; CI runs both. Reminders and escalations need the scheduler (`php artisan schedule:work` locally, cron in production).

## Conventions
- Asset/post naming: `CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#`, e.g. `ALMYRA_20270512_IG_REEL_FOOD_EL-EN_v2`. Enforced automatically on upload; raw inbox uploads use `ANY` for the platform and `IMG`/`VID` for the format (e.g. `ALMYRA_20261103_ANY_IMG_FOOD_EL-EN_v1.jpg`) because platform and post format are decided later on the post.
- Client brand profiles live in the `clients` table (`profile` JSON column in the `example.yaml` shape, `Client::toProfileArray()` returns the full shape). `config/clients/<slug>.yaml` files are imported with `php artisan clients:import`; `example.yaml` stays the template. Every client-owned table carries `client_id`.
- Caption prompt: `prompts/caption-draft.md`.
- Trend brief and batch plan prompts: `prompts/trend-research.md`, `prompts/content-plan.md`. `php artisan content:plan <client> --posts=N` drafts a reviewable batch; `--brief <file>` skips the web research and uses a pasted brief instead.

## Files
- `docs/research.md`: full research report with sources (tools, APIs, workflow, AI, architecture, risks).
- `docs/roadmap.md`: phases with checklists. Tick items as they finish.
- `docs/decisions.md`: decision log.
- `config/clients/example.yaml`: brand profile template.
- `prompts/caption-draft.md`: caption drafting prompt.
- `.claude/commands/`: `/kickoff` (first session), `/next` (continue the roadmap).
