---
name: social-ops
description: Social Ops — the agency's in-house Laravel organizer app (socialops.inglelandi.com): what it is, the 2026-11-04 pivot, status, decisions, hard rules and the thread workflow. Read first for any Social Ops work.
sources: [cowork]
aliases: [Social Ops, SocialOps, socialops.inglelandi.com, Social Ops app]
---
## What it is
- [stated] Custom Laravel app (D1) for the agency's hospitality clients: brand profiles, asset inbox, calendar, planning, Claude captions (EL/EN), client approval links, comments, reports; publishing drivers exist but are hidden
- [stated] Repo GitHub inglelandi/SocialOps (https://github.com/inglelandi/SocialOps, previously Kokouliss/SocialOps) is the source of truth; the inglelandi GitHub account is connected to this Claude account: read CLAUDE.md and docs/decisions.md first; prompts/ and config/clients/example.yaml
- [stated] Live at https://socialops.inglelandi.com on Panos's Plesk server (D3), see [[social-ops-hosting]]
- [stated] Pilot client Fuse Pharmacies (Cyprus pharmacy, slug fuse-pharmacies, code FUSE) (D4)
- [stated] Project memory handed over from Panos's Claude account on 2026-11-05 (old threads "Build Social Ops Phase 1 MVP" and "Convert Social Ops into agency organizer"); owner/requester is [[panos]]

## Pivot (2026-11-04)
- [stated] No longer a publisher but an organizing tool for the agency; publishing code stays behind FEATURE_PUBLISHING (off) "maybe we will need them in the future"
- [stated] Google Business Profile removed completely; platforms are Facebook, Instagram, TikTok, LinkedIn
- [stated] Claude works through the Social Ops MCP connector, not the server-side API, for now ("if things go well we convert to API again"); FEATURE_CLAUDE_API off, see [[social-ops-claude-connector]]
- [stated] Core flow: client profile + current trends (last30days approach) + user prompt + dates/count → plan of posts per date, plus Word export of the θεματολογία in the layout of Panos's "Θεματολογία posts November 26 Fuse Pharmacies.docx"
- [stated] His example Excels (Fuse Pharmacies deadlines sheet, all-clients SoMe accounts board) show how the team organizes today
- [stated] Comment inbox, numbers and listening report hide with publishing; ads budget sheet not built unless asked
- [stated] Skipped on purpose: deadline reminders by mail/Slack, Excel import of deadlines

## Status at handoff (2026-11-05)
- [stated] PRs #1–#121 merged, none open; what each added in [[social-ops-pr-history]]
- [stated] Full redesign done 2026-11-05 (Panos: "smooth and easy for the eye", "redesign everything"): dark side navigation, profile as sections in a side list
- [stated] UI overhaul was first priority; also more ways to capture client info (Basics / Agency / Voice and content / About) so generated posts are more accurate
- [stated] Holidays: no client selected = Greek only; with a client = the countries of that client's languages
- [stated] A client can have two or more accounts on one platform ("a client can have 2 instagrams")
- [stated] OPEN: rewrite of prompts/content-plan.md and caption-draft.md waits for one bad output from Panos plus his own version ("the prompts just don't feel right")
- [stated] OPEN on Panos's side: APP_DEBUG=false, SLACK_WEBHOOK_URL, MAIL_HOST fix (all editable on the admin Settings page)
- [stated] Open decisions: D5 extra caption languages, D6 portal branding

## Decisions
- [stated] D2: own drivers against the official APIs, no paid third-party services (Claude API and hosting fine; no Zernio, Ayrshare, Apify, ScrapeCreators, cookie scrapers)
- [stated] API Mega List (Apify actors) assessed: not a publishing option (cookie/password sessions break the OAuth-only rule)
- [stated] D10: interface in Greek and English with a per-login switch; client pages and mails follow the client's first language
- [stated] D15: link-in-bio page goes on the client's own WordPress site (application password stored encrypted); /bio/<slug> stays as fallback
- [stated] D16 publishing flag, D17 GBP out + LinkedIn in, D18 Claude via MCP with FEATURE_CLAUDE_API off (docs/decisions.md)
- [stated] LANGUAGE RULE: the agency is Greek, so reports (plan report, Word export) are fully Greek with no English mix, and "whatever you can make Greek, make Greek" across the app (lang/el.json, LocaleTest, APP_LOCALE=el)
- [stated] Alerts go to Slack (incoming webhook), Panos's choice over Telegram
- [stated] manouselis@inglelandi.com is admin; the admin-only Settings page edits .env values and feature flags because Panos cannot always edit .env
- [stated] Parked: Windows desktop app (PWA later), image/video generation and TTS (paid, Panos's call), Canva Brand Template autofill (needs Canva Enterprise), WhatsApp → inbox (needs Meta verification); Meta App Review and TikTok audit moot unless posting returns
- [stated] Not built on purpose: scraping, other platforms, PDF report, billing, white-label, RSS autopost, 2FA, aggregators
- [stated] Cost: API is cents per caption; a Claude subscription cannot power server jobs, the MCP connector covers chat-driven work on the plan

## Hard rules
- [stated] OAuth only (never store client passwords), live quota reads, EU-region storage, per-client isolation, human approval gate, human review of Greek captions
- [stated] Never write the Anthropic key anywhere but .env (Panos once pasted one in chat)

## Thread workflow
- [stated] Answer Panos in the language he used (Greek or English)
- [stated] One branch per thread, one PR at a time, branch restarted from origin/main after each merge; never open a PR against another open PR's branch
- [stated] Pint and the full test suite before every push; check the Pest JSON for "result":"passed" (a piped `php artisan test | tail` hides red tests)
- [stated] PR body has an "After merging" block (Plesk "Pull now", then /refresh); assign Panos; one reply per PR with the link
- [stated] Test helpers fakeClaude() and claudeRequests() in tests/Pest.php
