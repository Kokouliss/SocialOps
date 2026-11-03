# Social Ops: in-house social media system

## What this is
An internal tool for our agency (Chania, Crete) to plan, create, approve and publish social media posts for **many client accounts**, faster. Clients are mostly hospitality: villas, hotels, beach bars/lounges, restaurants, local brands. Content is **Greek and English**.

Goal: cut time from every step: asset intake, captions, translation, approvals, scheduling, reporting.

## Status
- Research done (November 2026): `docs/research.md`. Read it before proposing architecture or features.
- No code yet. Next step is Phase 1 (MVP) in `docs/roadmap.md`.
- Decisions taken so far and decisions still open: `docs/decisions.md`. **Ask the user about open decisions before writing code that depends on them.**

## Core idea
Own the workflow, rent the publishing.
- We build: client/brand profiles, asset inbox, content calendar, AI bilingual captions, client approval portal, reporting.
- We rent: publishing through a unified API (Zernio or Ayrshare) behind a `Publisher` adapter interface, so a direct Meta driver can replace it later.
- Reason: platform approvals (Meta App Review, TikTok audit, LinkedIn CM API, YouTube audit) take weeks to months. Start them in parallel (Phase 0) but do not block on them.

## MVP scope (Phase 1)
1. Client & brand profiles (accounts, voice, languages, pillars, hashtag bank, approval policy, approver contacts).
2. Asset inbox per client: no-login upload link, auto-naming, tags.
3. Content calendar with post state machine.
4. AI drafting: Claude API, EL + EN captions, per-platform variants, alt text, hashtags.
5. Client approval portal: signed expiring magic links, per-network previews, inline comments, approve on mobile, reminders.
6. Publishing to Instagram, Facebook, Google Business Profile via aggregator.
7. Token health monitor.

Out of MVP: TikTok, CSV import, evergreen queues, Canva autofill, auto-reports, auto-Reels, direct Meta driver, link-in-bio.

## Post state machine
`Idea → Draft → In Review → Changes Requested → Approved (locked) → Scheduled → Published | Failed | Manual-Needed`
- Approved posts are locked; editing one sends it back to In Review.
- Approval auto-schedules the post.
- Manual-Needed covers things no API can do (Instagram Story stickers, shopping tags, public TikTok before audit): notify a human to post in-app.

## Rules that must not be broken
- Never store client passwords. Official OAuth only.
- Never hard-code platform quotas. Read live limits (for example Instagram `content_publishing_limit`) before batch publishing.
- A human stays in the approval gate. AI drafts; it never publishes unapproved content.
- Greek captions get human review (gender agreement, idioms).
- Client data and media in EU-region storage. Log UGC repost permission and consent for people in assets.
- Each client is isolated: accounts, media, users, data.

## Conventions
- Asset/post naming: `CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#`, e.g. `ALMYRA_20270512_IG_REEL_FOOD_EL-EN_v2`. Enforced automatically on upload.
- Client brand profiles: `config/clients/<slug>.yaml` (see `example.yaml`) until they move into the database.
- Caption prompt: `prompts/caption-draft.md`.

## Files
- `docs/research.md`: full research report with sources (tools, APIs, workflow, AI, architecture, risks).
- `docs/roadmap.md`: phases with checklists. Tick items as they finish.
- `docs/decisions.md`: decision log.
- `config/clients/example.yaml`: brand profile template.
- `prompts/caption-draft.md`: caption drafting prompt.
- `.claude/commands/`: `/kickoff` (first session), `/next` (continue the roadmap).
