# Roadmap

Tick items as they finish. Effort numbers are estimates for 1–2 developers; re-check after the first two weeks.

## Phase 0: Platform access (start now, runs in parallel with code)
- [ ] Meta Business Verification for the agency Business Manager
- [ ] Meta developer app; request `instagram_content_publish` / Instagram Platform publishing, `pages_manage_posts`, `pages_read_engagement`, `pages_show_list`; record screencast; submit App Review
- [ ] Google Business Profile API access request form
- [ ] TikTok developer app; Content Posting API with `video.publish`; submit audit (needed by Phase 2)
- [ ] LinkedIn Community Management API: only if a client needs LinkedIn
- [ ] YouTube API audit: only if a client needs Shorts
- [ ] DPA template for clients; list sub-processors (aggregator, Anthropic, storage host)

## Phase 1: MVP (~6–8 weeks)
Exit: all current clients run one full month through the system.

- [ ] Resolve open decisions D1–D4 in `decisions.md`
- [ ] Project scaffold, auth for agency staff, EU-region storage bucket
- [ ] **Clients & brand profiles:** CRUD; fields from `config/clients/example.yaml`; per-client isolation
- [ ] **Asset inbox:** per-client no-login upload link (signed); auto-naming `CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#`; tags (pillar, season, people/consent, UGC permission)
- [ ] **Posts & calendar:** post model with state machine (see CLAUDE.md); month/week views per client and all clients; drag-and-drop reschedule
- [ ] **AI drafting:** Claude API call using `prompts/caption-draft.md` + brand profile + last approved captions as examples; outputs EL/EN, per-platform variants, alt text, hashtags; human edits before review
- [ ] **Approval portal:** signed expiring magic link per batch; Instagram/Facebook/GBP previews; inline comments; approve / request changes on mobile; lock on approve; auto-schedule on approve; reminders + SLA escalation; auto-approve on timeout for `optional` clients
- [ ] **Publisher adapter:** `publish(post, account)` interface; aggregator driver (Zernio or Ayrshare per D2); quota check; retries; webhook status updates; Manual-Needed notification
- [ ] **Token monitor:** daily job; expiry per account; alert 10 days before re-auth; re-auth link
- [ ] Pilot with 1 client for 2 weeks, then roll out to the rest

## Phase 2: Scale (~6–8 weeks)
Exit: monthly reports fully automatic; onboarding a new client takes under 1 hour.

- [ ] TikTok publishing (after audit passes; private-only until then)
- [ ] Bulk CSV / Google Sheet import into the calendar
- [ ] Evergreen queues with time slots (off-season)
- [ ] Canva Brand Template autofill for repeat graphics (menus, events, offers)
- [ ] Monthly auto-report: insights pull → Claude summary → branded PDF
- [ ] n8n intake: WhatsApp / Google Drive / Dropbox → asset inbox
- [ ] MCP endpoint so Claude can query the calendar and draft posts
- [ ] Content-pillar mix tracking per client, with drift warning

## Phase 3: Own the pipes & advanced AI (~8–12 weeks)
Exit: aggregator only used for long-tail platforms.

- [ ] Direct Meta driver (once App Review passes) behind the same adapter
- [ ] Auto-assembled Reels from venue clips (human final cut)
- [ ] Best-time-to-post suggestions from our own performance data
- [ ] Link-in-bio pages generated on WordPress per client
