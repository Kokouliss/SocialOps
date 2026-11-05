---
name: social-ops-pr-history
description: Social Ops PRs #1–#121 — what each batch added, plus the coding traps hit; read before building a feature so nothing is rebuilt or re-proposed.
sources: [cowork]
aliases: [Social Ops PRs]
---
## What exists
- [stated] #1–#14 MVP; #15–#17 pilot guide; #18 staff; #19 fixes; #20 refresh button; #21 exports; #22 styling; #23 planner; #24 dark mode; #25 design text
- [stated] Trend planner: `php artisan content:plan <client> --posts=N [--platforms] [--from] [--days] [--brief <file>]` makes Draft posts plus a review sheet (#4, Draft step #10); last30days cannot run on the server, a pasted brief is accepted; prompts in prompts/trend-research.md, content-plan.md, caption-draft.md
- [stated] Competitor batch 1 #26–#36: research sources, headline overlay, reports library, Slack, Jobs page, comment inbox, first comment, Google reviews, insights + monthly report, listening report, Withdraw
- [stated] Batch 2 #37–#48: needs-attention dashboard, posting slots, recurring posts, holidays + key dates, first image, Shorten, comment triage + saved replies, signatures, preflight, counter, follower snapshots, Import last 30 days
- [stated] OneUp batch #49–#64: client self-connect links, view-only client calendar, CSV import, Instagram grid, profile placeholders, collab posts, UTM, keyword auto-replies, monthly report mail, staff limited to clients (LimitClients), team notes with @FirstName Slack pings, TikTok/GBP numbers, IG/FB DMs, JSON API + webhooks (docs/api.md), MCP server (#63), Canva import
- [stated] Buffer/Planable UI batch #65–#80: client sub-nav and switcher, per-platform post form with live preview, calendar list view, filters and bulk actions, dashboard today/tomorrow, approval link progress + Approve all, duplicate post, asset grid, one state badge, empty states
- [stated] #81 Greek interface (lang/el.json, SetLocale); #82 "Change with Claude" + MCP update-post; #83 every profile field editable, notes → {{notes}}; #84 "Post from a prompt"; #85 PillarMix drift; #86 BestTimes; #87/#88 Drive and Dropbox → inbox; #89 Reels from clips (ffmpeg); #90 DPA template; #91 /bio/<slug>; #92 API error messages shown; #93 dropped web-search userLocation (GR rejected); #94 planning mails in rescue(); #95 link-in-bio on client WordPress
- [stated] Organizer conversion #96–#102: publishing flag, GBP out + LinkedIn in, MCP get-planning-brief/save-plan, Word export App\Reporting\TopicsDocx (WordprocessingML by hand, no PhpWord; client → Reports → Content plan row → "Download Word", only for plans filed through save-plan), profile tabs, clients board (Agency tab), deadlines per client + dashboard "due in 7 days" box
- [stated] #103 admin + Settings page; #104 GET /refresh; #105 Greek everywhere ({{plan_language}}); #106 OAuth connector; #107 get-caption-brief; #108 Claude queue + list-claude-jobs
- [stated] #109 calendar date picker + from–to range; #110 one grid per month, dates DD-MM-YYYY everywhere; #111 holidays (European, name days, world days, awareness months)
- [stated] Redesign #112–#114 (shell, profile, dashboard, board, calendar, post form; x-section/x-field components); #115 holidays by client language; #116 About section + voice.words in prompts with a "never invent a fact" rule; #117 Save bar on About, approval-mail failure is a warning not a 500; #118 Greek in placeholder names and pillar keys (slug and code stay Latin); #119 several accounts per platform, free-text client type with suggestions, words and phrases only "when they fit"; #120 pillars optional; #121 the profile-save 500 fix

## Traps and lessons
- [stated] Any regex on Greek text needs /u: `preg_split('/\R/')` without it cut a Greek υ (byte 0x85) → broken UTF-8 → JsonEncodingException (#121)
- [stated] Merge main into each branch before opening; parallel PRs collide in the sorted lang/el.json, so check `git merge-tree --write-tree` first
- [stated] Resolve composer.lock conflicts with `composer update <package>` on the branch, never GitHub's "Update branch" (it once dropped anthropic-ai/sdk and turned main red)
- [stated] ClientFactory gives a random client code; factory clients speak Greek first, so guest-page tests pin `withSession(['locale' => 'en'])`
- [stated] laravel/mcp tests: `SocialOpsServer::tool(X::class, $args)->assertOk()->assertSee()` and `Server::tools()->assertRegistered([...])`; TestResponse has no content()
- [stated] `{{x}}` inside a __() string in a Blade attribute compiles into an echo; pass it as a :tag built from '{'.'{x}}'
- [stated] Carbon max()/min() return an instance: copy before startOfWeek()
- [stated] A stale bootstrap/cache/routes-v7.php from a local route:cache makes tests miss new routes (route:clear)
- [stated] `assertDontSee('google')` hits fonts.googleapis.com; assertSee escapes &, so compare URLs with e(route(...)) and false
- [stated] actingAs sticks for the rest of a Pest test; throw_if evaluates its message eagerly; PHP `$a + $b` keeps the left keys
- [stated] UploadedFile::getMimeType() sniffs content, so fake downloads need real PNG/MP4 headers; `$response->json()` in an error message consumes the body stream
- [stated] Profile validation errors all come under one `profile` key; ClientProfile::scalarRules() is shared with clients:import, so rules are sometimes/nullable
- [stated] Screenshot demo data: sqlite, panos@example.com / password, port 8765
