# Social Media Ops for a Multi-Client Agency: Market Best Practices and In-House Build Plan

Research completed November 2026. Prices and API limits change often: re-check on vendor pages and official docs before relying on a number.

**Bottom line:** Build your own approval-first content pipeline (client brand profiles, asset intake, AI drafting in Greek and English, client approval links, a calendar), and get publishing either from a self-hosted engine (Mixpost Pro or Postiz) or from a pay-per-account unified API (Zernio/Late). Do not write raw Meta, TikTok, LinkedIn and YouTube integrations from scratch in phase one. The platform approval gates (Meta App Review, the TikTok audit, LinkedIn Community Management approval, the YouTube audit, Pinterest Standard access) take weeks to months. They are the real critical path, not the code. The biggest time savings come from approvals, captions/translation and asset intake, not from the act of scheduling.

## TL;DR

- **Copy the approval loop first.** Planable built a business on one insight: agency pain is approval, not scheduling. Copy its pixel-accurate previews, inline comments, "lock after approval" and "auto-schedule once approved". Copy Metricool's per-brand pricing logic (one workspace per client, no seat costs). Copy SocialPilot's and Sendible's white-label reports.
- **Rent publishing, own the workflow.** Meta's own APIs can publish Instagram feed posts, carousels, Reels and Stories (no stickers or links), plus Facebook Page posts and Reels. But Advanced Access requires Business Verification and App Review. TikTok keeps unaudited apps' posts private. LinkedIn Company Page posting needs Community Management API approval. A unified API (Zernio, per-account pricing since May 2026: first 2 accounts free, then $6/month for accounts 3–10, $3 for 11–100, $1 for 101–2,000) or self-hosted Mixpost Pro ($299 one-time) gets you live in days instead of months.
- **Phase it.** MVP in roughly 6–8 weeks for 1–2 developers: client profiles, asset inbox, AI bilingual captions, approval links, calendar, publishing to IG/FB/GBP through an aggregator. Phase 2 adds TikTok, reporting and Canva/template automation. Phase 3 brings direct API integrations only where the aggregator falls short.

## Key findings

1. **Approval is the agency bottleneck.** Planable markets itself around "review-and-approve without the screenshot-and-email dance". It offers no-approval, optional, required or multi-level flows, external client approvers, and auto-publish once approved. Planable's own blog (a competitor, not independent) says Buffer's approval workflow "is too basic to handle external client sign-offs, so teams end up running approvals in Slack, email, or Google Docs anyway."
2. **Per-seat pricing punishes agencies; per-client/per-brand is the model to copy.** As of October 2026, Sprout Social costs $79 (Essentials), $199 (Standard), $299 (Professional), $399 (Advanced) per seat per month annually, Standard capped at 5 profiles. Hootsuite's plans page (18 Oct 2026): $99 (Standard), $199 (Professional), $399 (Advanced) per user per month annually. Metricool charges by brand: $20/month annual for 5 brands up to $159 for 50 brands, with approvals, roles, API and Looker Studio on Advanced.
3. **The APIs are free but gated.** Meta, TikTok, LinkedIn, Google and Pinterest charge nothing for publishing APIs. The cost is review time. In August 2026 bundle.social (sells a competing API) reported "Meta is now explicitly telling developers to expect a 20-day wait for most submissions", versus 1–3 days for clean submissions until 2025. Not an official Meta timeline; another integrator reported about 10 days in October 2026.
4. **Some things cannot be published via API on any route:** Instagram Story stickers (link, poll, location, question, countdown), Reels longer than the API supports (guides say 90s, Meta's reference says up to 15 minutes, so test), shopping tags on API posts, and public TikTok posts from an unaudited app. The system needs a "manual publish with push reminder" fallback.
5. **Unified APIs and open-source schedulers are mature in 2026.** Postiz (AGPL-3.0, free self-hosted, MCP server for Claude) and Mixpost (Pro $299 one-time with workspaces, approval flow, API, MCP, Google Business Profile) are viable foundations. Ayrshare ($149–$599+/month) and Zernio (per-account) remove app-review work entirely.

## A) Commercial tool landscape

### Feature comparison (agency-relevant, late 2026)

| Tool | Standout for agencies | Approvals / client links | Pricing model (2026) | Weakness for us |
|---|---|---|---|---|
| **Planable** | Best approval UX: pixel-perfect previews, inline comments, version history, lock-after-approval, multi-level approvals (Enterprise) | Optional on Basic, Required on Pro; public links allow comments, formal approval needs a member account | Per workspace: Basic $33/mo annual ($39 monthly), Pro $49 ($59); unlimited users; 150 posts/workspace/mo on Pro | Per-workspace fee compounds across many small clients; analytics is a paid add-on |
| **Metricool** | Analytics + ads reporting, link-in-bio (SmartLinks), competitor tracking, Looker Studio, brand-based pricing | Approvals and roles on Advanced | Starter $20/mo (5 brands) → Advanced $53 (15), $85 (25), $159 (50), annual; X is a paid add-on | Mobile apps rated poorly; inbox limited |
| **Sprout Social** | Smart Inbox, listening, Trellis AI, deep reporting | From Standard | $79 / $199 / $299 / $399 per seat/mo annual (Oct 2026) | Far too expensive per seat for a small agency |
| **Hootsuite** | Breadth, enterprise analytics | With Advanced plan | $99 / $199 / $399 per user/mo annual (18 Oct 2026) | Per-user cost |
| **SocialPilot** | Cheapest white-label, client approval, bulk scheduling | Client approval + unlimited client seats on Premium | Premium $100/mo (white-label reports); Ultimate $200/mo (full white-label dashboard) | Less polished approval UX |
| **Sendible** | Fully white-labelled platform (login, emails, reports, custom domain) | Client portals | $29–$750/mo; white-label "starts at $268" per FAQ, may carry onboarding fee | White-label is expensive |
| **Vista Social** | All-rounder: multi-step approvals, listening, reviews, DM automation | Yes | Professional $79, Advanced $149, Scale $349–$379 (white-label) | White-label only on top plan; X not in white-label dashboard |
| **Ayrshare / Zernio (Late) / Upload-Post** | Developer APIs publishing to 13–16 networks through pre-approved apps | We build it | See section B | We build the UI |
| **Postiz / Mixpost** | Open-source / self-hosted schedulers we can extend | Mixpost Pro: multi-level approvals; Postiz: reviewers note no approval workflow | Postiz free (AGPL), hosted from $29; Mixpost Pro $299 one-time | We run servers and own app reviews if self-hosting |
| **Canva** | Brand Kit, Brand Templates, Bulk Create (CSV/XLSX, up to 300 rows), Magic Resize | Comments | Pro and above; Connect API Autofill on Pro+ | Autofill only works on Brand Templates with data fields |
| **Meta Business Suite (Planner)** | Free native scheduling for FB/IG incl. Reels and Stories | None for clients | Free | No multi-client approval layer, no Greek/English workflow |

Not verified in depth this cycle: Buffer, Later, Agorapulse, Loomly, Publer, ContentStudio, Iconosquare. Known points: Later "publishes no public posting API"; Planable (a competitor) criticises Buffer for per-channel pricing and weak external approvals; Loomly's white-label "Beyond" plan listed at $332/mo; Iconosquare's Excel plan listed at $139/mo. Trial before copying anything from these.

### Best-in-class features worth copying

| # | Feature | Who does it best | Why copy it | Priority |
|---|---|---|---|---|
| 1 | Pixel-accurate previews per network (feed, Reel, Story, carousel) | Planable | Clients approve what they see; fewer revision rounds | MVP |
| 2 | Approval states (none / optional / required / multi-level) per client | Planable, Mixpost Pro | Some clients want sign-off on everything, some trust us | MVP |
| 3 | Lock after approval + auto-schedule on approval | Planable | Removes "approved, then edited" errors and a manual step | MVP |
| 4 | Account-free client review link with inline comments and one-click approve | Planable (comment-only links), SocialPilot (client seats) | Hospitality owners won't create accounts; do better with signed magic links | MVP |
| 5 | Workspace-per-client isolation (accounts, media, permissions) | Mixpost, Planable, Metricool | Scales with new clients; clean offboarding | MVP |
| 6 | Bulk CSV / sheet import into the calendar | SocialPilot, Canva Bulk Create, Zernio API | Seasonal campaigns batch-built in one go | Phase 2 |
| 7 | First comment, collaborators, trial Reels, location tags | Meta API; Zernio and Blotato expose `firstComment` | Collab posts with partner venues double reach | MVP (via aggregator) |
| 8 | Posting queues / evergreen recycling with time slots | Mixpost "Posting Schedule (Queue)" | Off-season filler runs itself | Phase 2 |
| 9 | Brand-level reporting with white-label PDFs | Metricool (depth), SocialPilot (cheap white-label) | Monthly client reports become automatic | Phase 2 |
| 10 | Brand templates autofilled from data | Canva Brand Templates + Autofill API | Menus, events, "villa of the week" graphics from a sheet | Phase 2 |
| 11 | AI assistant in the composer + MCP for external agents | Postiz (MCP server), Mixpost (AI Assistant, MCP), Planable (MCP connector, Brand Context) | Claude can draft, schedule and report directly | MVP (drafting), Phase 2 (MCP) |
| 12 | Link-in-bio page | Metricool SmartLinks | We build WordPress sites: generate it per client | Phase 3 |

## B) Platform APIs and technical feasibility

| Platform | What can be published via API | Key limits | Access gates | Can't do via API | Priority for hospitality |
|---|---|---|---|---|---|
| **Instagram** (Graph API / Instagram Platform) | Single images (JPEG), carousels (2–10, mixed), Reels, Stories; collaborators (up to 3, feed/Reels/carousels, not Stories); user tags; alt text; location; trial Reels; `share_to_feed`; cover image | 100 API posts per account per rolling 24h (carousel = 1), but docs also say 50 in places: read `content_publishing_limit`; containers expire after 24h; media must be at a public URL | Business/Creator account; Advanced Access needs App Review (screencast) and Business Verification; Instagram Login path no longer needs a linked FB Page | Story stickers; shopping tags; Story captions | **Critical** |
| **Facebook Pages** | Feed posts, photos, videos, Reels (`/video_reels`, resumable upload via rupload.facebook.com), Stories, Reel collaborator invites, location tags; video crossposting across Pages | Reels: 30 API-published per Page per 24h; Graph API v26.0 current | `pages_manage_posts` (+ `pages_read_engagement`, `pages_show_list`); App Review; Page access token | Personal profiles, Groups | **Critical** |
| **TikTok** (Content Posting API) | Direct Post of video and photo posts; or MEDIA_UPLOAD/inbox so the creator finishes in-app | Per-creator daily cap (unpublished); unaudited apps limited to 5 posting users/24h | `video.publish` scope; **audit required**, else all posts forced private (SELF_ONLY); UI must show creator info, preview, commercial-content disclosure, express consent | Public posts before audit; promotional watermarks | **High** |
| **Google Business Profile** | Local Posts: standard, event, offer; recurring posts (April 2026); reviews and replies; performance metrics | Quota assigned on approval | Access request form; free but not automatic | — | **High** (restaurants, bars) |
| **LinkedIn** | Company Page posts (text, image, video, carousel, polls) | Development tier 500 calls/app/24h; tokens 60 days | `w_organization_social` needs Community Management API approval (Development → Standard, screencast); registered legal entity; upgrade within 12 months | — | Low |
| **YouTube Shorts** | `videos.insert` (vertical, Shorts length) | Since 1 June 2026: 100 `videos.insert` calls/day per project in a separate bucket, + 10,000 units for everything else | OAuth verification; compliance audit, unverified uploads may be locked private (docs contradict each other) | — | Medium |
| **Threads** | Text, image, video, carousels (up to 20) | 250 posts + 1,000 replies per profile per 24h; 500 chars; 60-day tokens | `threads_basic`, `threads_content_publish`; App Review for others' accounts | — | Low–medium |
| **Pinterest** (API v5) | Image and video Pins to boards | Trial: Pins visible only to creator | Standard access via review with OAuth flow video | Carousels | Low (villas, wedding venues) |

### Token management for many clients
- Meta long-lived, Threads and LinkedIn tokens last about 60 days. Pinterest v5 uses refresh tokens. Build a token-health job: refresh proactively, store expiry per account, email the account manager (and client if needed) 10 days before re-authorisation is required.
- LinkedIn: posting depends on the authorising person keeping a Page admin role. If they leave, publishing stops. Store who authorised what.
- Read live quotas (`content_publishing_limit`, `threads_publishing_limit`) before batch publishing; never hard-code.

### Build accelerators

| Option | Cost (2026) | Pros | Cons |
|---|---|---|---|
| **Zernio (formerly Late, getlate.dev)** | First 2 accounts free; 3–10 $6/mo each, 11–100 $3, 101–2,000 $1; profiles free; full API on every tier | Cheapest for agencies; pre-approved Meta/TikTok apps; first comment, collaborators, trial Reels; queue; 16 platforms | Third-party dependency; mixed Trustpilot feedback; X API pass-through costs |
| **Ayrshare** | Premium $149/mo (1 profile), Launch $299 (10), Business $599 (30, then $8.99/profile 31–100); 28-day trial | Most complete (publishing, analytics, comments, DMs, reviews), MCP server, users skip Meta App Review | Expensive at small scale; 1,000 posts/mo on Business per a competitor |
| **Upload-Post** | From $24/mo ($16 annual); e.g. 75 profiles at $147/mo (vendor claim) | Cheap, n8n/Make friendly | Smaller vendor |
| **Mixpost Pro (self-hosted, Laravel)** | $299 one-time (1 domain, 1 year updates; optional $239/yr); Enterprise $1,199 | Unlimited workspaces/users, multi-level approvals with Viewer-role approvers, queue, AI assistant, API, MCP, webhooks; FB, IG (posts/Reels/Stories), LinkedIn, YouTube, TikTok, Pinterest, Threads, GBP; source-available; PHP fits a WordPress shop | We pass each platform's app review ourselves; we run Redis, MySQL, queues |
| **Postiz (self-hosted, AGPL-3.0)** | Free self-hosted; hosted from $29/mo | 14–20+ networks, public API, MCP server and CLI for Claude agents, active development | AGPL obligations if modified and offered as a service; no approval workflow; we own app reviews; Next.js/NestJS/Temporal stack |
| **Fully custom direct integrations** | Dev time only | Full control, no vendor fees | Months of reviews per platform; ongoing API-version maintenance |

**Recommendation:** Zernio (or Ayrshare for analytics/comments/reviews in one API) as the publishing layer behind our own app. In parallel, start Meta Business Verification and App Review so Meta can come in-house later. If self-hosting everything, Mixpost Pro fits better than Postiz: approvals and workspaces built in, PHP stack we know.

## C) Workflow and ops best practices

These are standard agency operating patterns plus recommendations for our context, not findings from a specific study.

**Monthly cadence (per client)**
- **Week −4:** 30-minute strategy pass. Pillars and key dates (openings, events, Greek Easter, 15 August, fully booked periods, off-season offers).
- **Week −3:** asset intake deadline. Client uploads to their portal inbox. Fill gaps with a shoot day.
- **Week −2:** batch creation day: all graphics and Reels for 3–4 clients per day, then AI caption drafting in EN and EL.
- **Week −2 to −1:** client approval window, 48-hour SLA. Silence after a second reminder = auto-approve for clients on the "optional" tier.
- **Ongoing:** reactive Stories (specials, sunsets, live events) through a fast lane: mobile capture, same-day approval or pre-authorised posting.
- **Month end:** auto-generated report + 15-minute review.

**Roles (small team):** strategist/account lead (calendar, client relationship); creator (design, edit); publisher/QA (final checks, scheduling, token health). One person can hold two roles; the system enforces handoffs, not headcount.

**Content pillars for hospitality:** Place (views, rooms, venue); Food & drink; People (staff, owner stories); Guests/UGC (reposts with permission); Local Crete (beaches, villages, events); Offers/booking prompts. Map each client to 4–5 pillars and a weekly mix; flag drift.

**Biggest time sinks and fixes**
1. Chasing assets from venues → per-client no-login upload link with prompts ("3 vertical videos of tonight's sunset"), auto-tagging, reminder cadence.
2. Approval ping-pong over WhatsApp/email → one review link, inline comments, version history, lock on approval, SLA reminders.
3. Writing captions twice (EL and EN) → AI drafts both from one brief using the client voice profile; human edits.
4. Resizing for every format → design masters in 9:16 and 4:5 with safe zones, auto-derive 1:1 and 16:9.
5. Manual reporting → scheduled report generation with AI commentary.
6. Re-authorising broken accounts → proactive token monitor.

**Naming convention:** `CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#`, e.g. `ALMYRA_20270512_IG_REEL_FOOD_EL-EN_v2`. Enforced on upload.

**Hospitality specifics**
- Seasonality: pre-season (March–May) for booking intent, in-season live content (June–September), evergreen queues off-season (November–February).
- Multilingual: Greek for locals, English default for tourists; German, French or Italian for specific villas, AI-generated and spot-checked.
- UGC: written repost permission (a DM reply is enough), logged against the asset. Use Instagram collaborator posts with partner venues and creators.
- Reels: short vertical clips of arrival, view, plate, pour. Pre-built edit templates per client.

## D) AI and automation opportunities

| Step | Automation | Tooling | Notes |
|---|---|---|---|
| Captions | Per-client voice profile (tone, emoji policy, banned words, CTA style, approved examples) in every Claude call; EL + EN + per-platform variants (IG 2,200 chars, Threads 500) | Claude API | Store approved captions as few-shot examples so the voice improves |
| Translation EL↔EN | LLM drafts both languages; native speaker reviews | Claude/GPT; DeepL as second opinion | Alconost May 2026 vendor data (5,632 evaluations, 85 pairs, not Greek-specific): Gemini 77.7 AQI vs DeepL 70.8, Claude 75.6, GPT 73.1. WMT24++: "LLMs are the best-performing MT systems in all 55 languages", pending human evaluation. 2025 EN→EL gender-bias study: DeepL strongest on explicit feminine forms, GPT-4o better on gender-ambiguous sentences. Keep human review for Greek |
| Hashtags | LLM picks from per-client curated bank + location tags | Claude | Small, relevant sets |
| Alt text | Vision model; IG API accepts `alt_text` | Claude vision | Accessibility + SEO |
| Resizing/reframing | Master-to-variant rendering; smart crop | Canva Magic Resize / Autofill API (60 autofill/min, 20 exports/min), or FFmpeg + subject detection | Respect 9:16 safe zones |
| Templated graphics | Sheet → Brand Template → images | Canva Autofill API (Pro+), Orshot-style renderers | Menus, events, offers |
| Short video | Auto-assemble Reels from clips (beat-synced cuts, text overlays) | FFmpeg pipelines or video APIs | Phase 2–3; human final cut |
| Approvals | Reminders, SLA escalation, auto-approve on timeout (optional tier), auto-schedule on approve | Our app + n8n | Biggest measurable saver |
| Reporting | Insights → AI summary → branded PDF | Aggregator analytics + Claude | |
| Agents | Claude via MCP drafting and scheduling | Postiz, Mixpost, Ayrshare, Zernio ship MCP/agent tooling | Humans stay in the approval gate |

**Automation platforms:** n8n (self-hostable, good for EU data control), Make, Zapier for edges: intake from Google Drive/Dropbox/WhatsApp, notifications. Core content state lives in our app's database, not in the automation tool.

## Time-savings opportunities ranked

| Rank | Opportunity | Impact | Effort | Why |
|---|---|---|---|---|
| 1 | Client approval portal: magic links, inline comments, lock + auto-schedule | Very high | Medium | Kills the WhatsApp/email loop |
| 2 | AI bilingual captions with per-client voice | Very high | Low | Halves writing work |
| 3 | Asset intake inbox with auto-naming/tagging | High | Low–medium | Ends asset chasing |
| 4 | Publishing via aggregator (IG/FB/GBP first) | High | Low | No app reviews on day one |
| 5 | Batch calendar + CSV/sheet import | High | Medium | Seasonal campaigns in one pass |
| 6 | Auto-reporting with AI commentary | Medium–high | Medium | Monthly hours per client recovered |
| 7 | Template autofill graphics (Canva API) | Medium | Medium | Repetitive promos automated |
| 8 | Auto alt text + hashtags | Medium | Low | Small, on every post |
| 9 | Evergreen queues off-season | Medium | Low–medium | Winter content on autopilot |
| 10 | Auto-generated Reels from raw footage | Medium–high | High | Quality control is hard |

## E) Recommended architecture

### Core modules
1. **Client & Brand Profiles:** connected accounts, voice guide, languages, pillars, hashtag bank, approval policy (none/optional/required, SLA hours), approver contacts, key dates.
2. **Asset Library:** per-client buckets (S3-compatible, EU region), auto-naming, tags (pillar, season, people/consent), UGC permission log, derived renditions.
3. **Content Calendar:** month/week per client and all-clients, drag-and-drop, statuses `Idea → Draft → In Review → Changes Requested → Approved (locked) → Scheduled → Published / Failed / Manual-Needed`.
4. **AI Drafting Service:** Claude prompts built from brand profile + examples; EL/EN and per-platform variants, alt text, hashtags.
5. **Client Approval Portal:** signed, expiring links; per-network previews; comment and approve on mobile; reminders and escalation.
6. **Publisher Adapter:** one interface `publish(post, account)` with drivers: Zernio/Ayrshare first, direct Meta later. Quota check, retries, webhooks, manual-fallback notifications.
7. **Token & Health Monitor:** expiry tracking, re-auth links.
8. **Analytics & Reporting:** nightly insight pulls, monthly branded PDF with AI narrative.

### Tech stack options

| Option | Description | Fit |
|---|---|---|
| **A. Custom app + unified API (recommended)** | Laravel (PHP, matches WordPress skills) or Next.js; Postgres; Redis queue; S3-compatible EU storage; Zernio/Ayrshare; Claude API; n8n for intake/notifications | Fastest path to our exact workflow; vendor cost scales per account |
| **B. Extend Mixpost Pro** | Buy Mixpost Pro ($299), use its workspaces, approvals, queue, publishing; add AI drafting, intake portal, reporting as Laravel packages | Cheapest feature-complete base; we clear each app review ourselves |
| **C. Fork Postiz** | Self-host, add approvals and portal | Missing approvals, AGPL constraints; not recommended |
| **D. WordPress plugin** | Build inside WP admin | Not recommended: cron, queues and media processing are poor fits |

### Phased roadmap
See `roadmap.md` for the checklist version.

| Phase | Scope | Effort (1–2 devs) | Exit criteria |
|---|---|---|---|
| **0: Paperwork (now, in parallel)** | Meta Business Verification + App Review; TikTok app + audit; GBP API access form; LinkedIn CM API and YouTube audit only if needed | Weeks to months of calendar time | Approvals in hand before Phase 3 |
| **1: MVP** | Profiles, asset inbox, calendar, Claude EL/EN drafting, approval portal, IG + FB + GBP via aggregator, token monitor | ~6–8 weeks | All current clients run one full month through it |
| **2: Scale** | TikTok, CSV import, evergreen queues, Canva autofill, auto-reports, alt text, n8n intake from WhatsApp/Drive, MCP endpoint | ~6–8 weeks | Reports fully automatic; new client onboarding < 1 hour |
| **3: Own the pipes & advanced AI** | Direct Meta driver, auto-Reel assembly, best-time suggestions, link-in-bio on WordPress | ~8–12 weeks | Aggregator only for long-tail platforms |

Effort estimates are judgement for a small team, not a benchmark. Validate after a two-week spike.

### Key risks and mitigations
- **API access delays:** Meta reportedly ~20-day App Review waits, multiple rounds likely; TikTok posts private until audit. → Aggregator in Phases 1–2.
- **Rate limits and doc conflicts:** Instagram docs say 100 and 50 posts/24h in different places; YouTube docs contradict on private-locking. → Read live quota endpoints, test.
- **Account bans/flags:** official OAuth only, never client passwords, no unofficial automation.
- **Token expiry:** 60-day cycles on Meta, Threads, LinkedIn. → Monitor + re-auth links.
- **Vendor risk:** aggregators change pricing (Late → Zernio, per-account in May 2026). → Keep the publisher adapter.
- **Policy changes:** quotas revised often (YouTube upload bucket, June 2026). → Subscribe to changelogs, quarterly review.
- **GDPR/EU:** we're a processor for client data and guests' faces in UGC. DPAs with each client and sub-processors (aggregator, Anthropic, storage). EU-region storage. Consent log for UGC and people in assets. Retention rules for raw footage. Check each US vendor's EU-US Data Privacy Framework or SCC basis.
- **AI quality in Greek:** gender agreement and idioms. → Mandatory human check for Greek until approval-edit rates fall.

## Caveats
- Many API figures come from integrator blogs (Postproxy, Outstand, Blotato, Upload-Post, bundle.social) that sell competing products; cross-checked against Meta, TikTok, Google and Pinterest docs where possible.
- Tool prices change often; reviewers report slightly different numbers (e.g. Vista Social Scale at $349, $379 or $449). Confirm on vendor pages.
- Unconfirmed claims ("Planable acquisition 2026", Instagram API call-limit cuts from a single blog) were left out or flagged.
- Buffer, Later, Agorapulse, Loomly, Publer, ContentStudio, Iconosquare not verified in depth: research gap, not a verdict.

## Sources
- [Planable for agencies (bestsocialmediascheduler)](https://bestsocialmediascheduler.com/reviews/planable-for-agencies)
- [Planable: agency workflow software](https://planable.io/blog/agency-workflow-software/)
- [Metricool pricing (socialrails)](https://socialrails.com/blog/metricool-pricing)
- [Metricool review (brandid)](https://brandid.app/blog/metricool-review/)
- [Instagram API pricing (outstand)](https://www.outstand.so/blog/instagram-api-pricing)
- [Does LinkedIn have an API (social-api)](https://social-api.ai/blog/does-linkedin-have-an-api)
- [Pinterest (outstand)](https://www.outstand.so/pinterest)
- [TikTok Content Posting API: Direct Post](https://developers.tiktok.com/docs/en/content-posting-api-reference-direct-post)
- [Instagram Reels API publishing guide (postproxy)](https://postproxy.dev/blog/instagram-reels-api-publishing-guide/)
- [Instagram Reels & Stories API (outstand)](https://www.outstand.so/blog/instagram-reels-stories-api)
- [Instagram limits (postdaily)](https://www.postdaily.app/limits/instagram)
- [Mixpost pricing](https://mixpost.app/pricing)
- [Postiz](https://postiz.com/)
- [Facebook Reels API (Ayrshare)](https://ayrshare.com/facebook-reels-api-how-to-post-fb-reels-using-a-social-media-api)
- [Ayrshare pricing 2026 (social-api)](https://social-api.ai/blog/ayrshare-pricing-social-media-api-2026)
- [Instagram/Facebook API without Meta App Review (social-api)](https://social-api.ai/blog/instagram-facebook-api-without-meta-app-review)
- [Best social media approval tools (fs-poster)](https://www.fs-poster.com/blog/best-social-media-approval-tools)
- [Planable review (bestsocialmediascheduler)](https://bestsocialmediascheduler.com/reviews/planable)
- [Planable acquisition 2026 (toolsurf, unconfirmed)](https://www.toolsurf.com/planable-acquisition-2026/)
- [Best white-label social media software for small agencies 2026 (aidelly)](https://www.aidelly.ai/blog/best-white-label-social-media-software-small-agencies-2026)
- [Tools with white-label reports (postplanify)](https://postplanify.com/blog/best-social-media-tools-with-white-label-reports)
- [SocialPilot alternatives (Planable)](https://planable.io/blog/socialpilot-alternatives/)
- [Best white-label social media management tools (postplanify)](https://postplanify.com/blog/best-white-label-social-media-management-tools)
- [Sendible alternatives (maevesocial)](https://maevesocial.com/blog/best-sendible-alternatives)
- [Zernio/Late pricing docs](https://docs.getlate.dev/pricing)
- [Ayrshare (heytasky)](https://heytasky.com/tools/ayrshare/)
- [Open-source social media tools for startups (Mixpost)](https://mixpost.app/blog/why-open-source-social-media-management-tools-are-perfect-for-startups)
- [Postiz (scheduler.social)](https://scheduler.social/schedulers/postiz)
- [Canva template to API (Orshot)](https://orshot.com/solutions/canva-template-to-api)
- [Best social media scheduling API (adaptlypost)](https://adaptlypost.com/blog/best-social-media-scheduling-api)
- [Mixpost features](https://mixpost.app/features)
- [Later alternatives (Planable)](https://planable.io/blog/later-alternatives/)
- [Instagram posting API (Blotato)](https://www.blotato.com/blog/instagram-posting-api)
- [Canva developers](https://canva.dev/)
- [Gist: jameschapman2c](https://gist.github.com/jameschapman2c/65eff9f54a2d350b17a6ce5127b9fe42)
- [Instagram API integration guide (postzen)](https://www.postzen.dev/blog/instagram-api-integration-guide)
- [Instagram API publishing limits (reeluploader)](https://reeluploader.com/blog/instagram-api-publishing-limits)
- [Meta Advanced Access (postgate)](https://postgate.studio/blog/meta-advanced-access)
- [Facebook Reels API (Ayrshare blog)](https://www.ayrshare.com/blog/facebook-reels-api-how-to-post-fb-reels-using-a-social-media-api/)
- [Meta: Reels publishing guide](https://developers.facebook.com/documentation/video-api/guides/reels-publishing)
- [Ayrshare Facebook API](https://www.ayrshare.com/facebook-api.md)
- [Meta: Reels publishing (md)](https://developers.facebook.com/documentation/video-api/guides/reels-publishing.md)
- [facebook-api.latest.md (GitHub)](https://github.com/antler-labs-ng/platform/blob/master/facebook-api.latest.md)
- [Postiz: TikTok provider](https://docs.postiz.com/self-host/providers/tiktok)
- [TikTok content sharing guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines/)
- [TikTok photo post API](https://developers.tiktok.com/doc/content-posting-api-reference-photo-post)
- [Google Business Profile API: latest updates](https://developers.google.com/my-business/content/latest-updates?no_redirect=true)
- [GBP API documentation 2026 (slashpost)](https://slashpost.ai/blogs/google-business-profile/google-business-profile-api-documentation-2026)
- [LinkedIn: increasing access](https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access?view=li-lms-2026-10)
- [LinkedIn Community Management API access](https://singhamandeep.com/linkedin-community-management-api-access/)
- [LinkedIn API pricing (outstand)](https://www.outstand.so/blog/linkedin-api-pricing)
- [LinkedIn API (salesforge)](https://www.salesforge.ai/blog/linkedin-api)
- [YouTube API pricing (Blotato)](https://www.blotato.com/blog/youtube-api-pricing)
- [YouTube: quota and compliance audits](https://developers.google.com/youtube/data/guides/quota_and_compliance_audits)
- [YouTube API quota (outstand)](https://www.outstand.so/blog/youtube-api-pricing-quota)
- [Meta: Threads API overview](https://developers.facebook.com/documentation/threads/overview)
- [Post to Threads via API (postproxy)](https://postproxy.dev/blog/how-to-post-to-threads-via-api/)
- [Threads limits (bulkpublish)](https://www.bulkpublish.com/blog/threads-limits/)
- [Threads API tutorial (chirio)](https://blog.chirio.dev/blog/threads-api-tutorial)
- [Pinterest access tiers](https://developers.pinterest.com/docs/key-concepts/access-tiers/)
- [Pinterest Pin API (bundle.social)](https://bundle.social/blog/pinterest-pin-api)
- [Pinterest API (vorplabs)](https://vorplabs.com/agent-tools/pinterest-api)
- [Pinterest API v5 transition (Ayrshare)](https://www.ayrshare.com/solutions/pinterest-api-v5-transition-the-complete-developer-guide.md)
- [LinkedIn API limits 2026 (activepieces)](https://www.activepieces.com/blog/linkedin-api-access-limits-and-errors-in-2026)
- [Ayrshare alternatives (opentweet)](https://opentweet.io/alternatives/ayrshare)
- [Ayrshare alternative pricing (plugkit)](https://www.plugkit.co/blog/ayrshare-alternative-pricing/)
- [bundle.social vs Ayrshare](https://info.bundle.social/comparison/ayrshare)
- [Later alternative (Upload-Post)](https://www.upload-post.com/later-alternative/)
- [Ayrshare pricing (Upload-Post)](https://www.upload-post.com/ayrshare-pricing/)
- [Mixpost alternative (Upload-Post)](https://www.upload-post.com/mixpost-alternative/)
- [Social media management tools comparison (Mixpost)](https://mixpost.app/blog/social-media-management-tools-comparison)
- [Mixpost docs](https://docs.mixpost.app/using/)
- [Postiz (doolpa)](https://doolpa.com/article/postiz)
- [Postiz open-source scheduler (devopspack)](https://devopspack.com/postiz-open-source-social-media-scheduler/)
- [data-machine-socials issue #275 (GitHub)](https://github.com/Extra-Chill/data-machine-socials/issues/275)
- [Post to Instagram via API (postproxy)](https://postproxy.dev/blog/post-to-instagram-via-api/)
- [Canva Connect API guide 2026 (activepieces)](https://www.activepieces.com/blog/canva-api-how-the-connect-api-works-2026-guide)
- [Canva Connect: autofills](https://www.canva.dev/docs/connect/api-reference/autofills/)
- [Mixpost MCP docs](https://docs.mixpost.app/mcp/)
- [Late (heytasky)](https://heytasky.com/tools/late/)
- [YouTube API quota exceeded (bundle.social)](https://bundle.social/blog/youtube-api-quota-exceeded-limits-fixes)
- [EN→EL gender bias in MT (arXiv 2506.09558)](https://arxiv.org/pdf/2506.09558)
- [Planable (toolwise)](https://toolwise.ai/tools/planable)
- [Instagram API rate limits 2026 (keyapi)](https://www.keyapi.ai/blog/instagram-api-rate-limits-2026-what-changed-and-how-to-adapt/)
