# Roadmap

Tick items as they finish. Effort numbers are estimates for 1–2 developers; re-check after the first two weeks.

## Phase 0: Platform access (start now, runs in parallel with code)
- [ ] Meta Business Verification for the agency Business Manager
- [ ] Meta developer app; request `instagram_content_publish` / Instagram Platform publishing, `pages_manage_posts`, `pages_read_engagement`, `pages_show_list`; record screencast; submit App Review
- [ ] Google Business Profile API access request form (required: our own GBP driver)
- [ ] TikTok developer app; Content Posting API with `video.publish`; submit audit (required for public TikTok posts; private-only until it passes)
- [ ] DPA template for clients; list sub-processors (Anthropic, storage host)

## Phase 1: MVP (~8–11 weeks; the four direct drivers add roughly 2 to 3 weeks over an aggregator)
Exit: all current clients run one full month through the system.

- [ ] Resolve open decisions D1–D4 in `decisions.md`
- [x] Project scaffold (Laravel 13, Pest, Horizon, CI) and auth for agency staff (login only, `php artisan staff:create`)
- [ ] EU-region storage bucket (depends on D3)
- [ ] Meta app in dev mode, TikTok sandbox app, GBP test listing, agency test accounts, so every driver is tested before approvals land
- [x] **Clients & brand profiles:** CRUD; fields from `config/clients/example.yaml`; per-client isolation (every client-owned table carries `client_id`; `php artisan clients:import` loads YAML profiles)
- [x] **Asset inbox:** per-client no-login upload link (signed); auto-naming `CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#`; tags (pillar, season, people/consent, UGC permission)
- [x] **Posts & calendar:** post model with state machine (see CLAUDE.md); month/week views per client and all clients; drag-and-drop reschedule
- [x] **AI drafting:** Claude API call using `prompts/caption-draft.md` + brand profile + last approved captions as examples; outputs EL/EN, per-platform variants, alt text, hashtags; human edits before review. "Draft captions with Claude" button on the post editor (`POST /posts/{id}/draft`), reviewer notes shown on the form
- [x] **Trend-aware batch planning:** `php artisan content:plan <client> --posts=N` researches trends (Claude web search, `prompts/trend-research.md`), plans N posts by pillar quota and key dates (`prompts/content-plan.md`), drafts every caption with `CaptionDrafter` (reusable for the AI drafting item above), creates one Draft post per plan with a variant per platform and language on the calendar, and saves a review sheet under `plans/<client>/`
- [x] **Approval portal:** signed expiring magic link per batch; Instagram/Facebook/TikTok/GBP previews; inline comments; approve / request changes on mobile; lock on approve; auto-schedule on approve; reminders + SLA escalation; auto-approve on timeout for `optional` clients. Staff page `/clients/{id}/reviews` sends a batch (`Review`) and reads the answers; the client's page is `/review/{id}` behind the signed link; `reviews:remind` runs hourly from the scheduler
- [x] **Publisher adapter and drivers:** `publish(post, account)` interface; `MetaPublisher` (Instagram + Facebook via Graph API: image, carousel, Reel, Story; container flow, resumable video upload), `TikTokPublisher` (Content Posting API direct post, video and photos; private-only until audit), `GbpPublisher` (local posts: update, event, offer); OAuth connect flow per platform; live quota check; retries; status polling or webhooks; Manual-Needed notification; TikTok composer shows preview, commercial-content disclosure and the creator's consent, as TikTok requires. Built in dev mode against the official APIs (`app/Publishing`), tokens in `accounts`, `posts:publish-due` every minute queues `PublishPost` (3 tries, then Failed + mail); needs the Phase 0 app ids in `.env` and the agency's test accounts to try for real
- [ ] **Token monitor:** daily job; expiry per account; alert 10 days before re-auth; re-auth link
- [ ] Pilot with 1 client for 2 weeks, then roll out to the rest

## Phase 2: Scale (~6–8 weeks)
Exit: monthly reports fully automatic; onboarding a new client takes under 1 hour.

- [ ] Bulk CSV / Google Sheet import into the calendar
- [ ] Evergreen queues with time slots (off-season)
- [ ] Canva Brand Template autofill for repeat graphics (menus, events, offers)
- [ ] Monthly auto-report: insights pull → Claude summary → branded PDF
- [ ] n8n intake: WhatsApp / Google Drive / Dropbox → asset inbox
- [ ] MCP endpoint so Claude can query the calendar and draft posts
- [ ] Content-pillar mix tracking per client, with drift warning

## Phase 3: Advanced AI (~6–8 weeks)
Exit: Reels assembly and best-time suggestions in use by every client.

- [ ] Auto-assembled Reels from venue clips (human final cut)
- [ ] Best-time-to-post suggestions from our own performance data
- [ ] Link-in-bio pages generated on WordPress per client
