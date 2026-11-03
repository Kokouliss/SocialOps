# Social Ops

Internal system for planning, creating, approving and publishing social media content across all our client accounts.

## Start here
1. Open this folder in Claude Code (`cd social-ops` then `claude`).
2. Run `/kickoff`. Claude reads the research and asks you the open decisions before writing any code.
3. After that, use `/next` to continue with the next roadmap item.

## Do this now, outside the code (Phase 0)
We publish through our own platform apps (no paid middleman), so nothing goes live on a platform until its approval passes. Approvals take weeks, so start them today:
- [ ] Meta Business Verification for the agency's Business Manager
- [ ] Meta developer app + App Review (Instagram and Facebook publishing permissions)
- [ ] Google Business Profile API access request form (required: we publish through the GBP API ourselves)
- [ ] TikTok developer app + Content Posting API audit (required for public TikTok posts; private-only until it passes)
- [ ] Sign Data Processing Agreements with each client (GDPR)

## Dev setup
PHP 8.3, Composer, Node 22, Postgres, Redis. Then:
```
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate && npm install && npm run build
php artisan staff:create "Your Name" you@agency.gr
php artisan serve
```
`php artisan test` runs the suite; `php artisan horizon` runs the queue; `php artisan schedule:work` runs the scheduler (cron in production).

## Publishing
Each client's accounts are connected on its Accounts page through the platform's own login (our Meta app, TikTok app and Google Cloud project from Phase 0; ids in `.env`, redirect URIs `APP_URL/oauth/{meta,tiktok,google}/callback`). Every minute `posts:publish-due` queues a `PublishPost` job for each Scheduled post whose time has come; the job asks Instagram for its live publishing limit, publishes platform by platform, retries twice on platform errors, then marks the post Failed and e-mails the sender. Things no API can do (a public TikTok before our audit, a video on Google Business) land in Manual-Needed with an e-mail. Media must be reachable by the platforms: an EU S3 bucket in production (signed URLs), a public `APP_URL` in development; TikTok also wants the media domain verified in its developer portal.

## Folder map
| Path | What |
|---|---|
| `CLAUDE.md` | Project brief Claude Code reads every session |
| `docs/research.md` | Full research report with sources |
| `docs/roadmap.md` | Phased plan with checklists |
| `docs/decisions.md` | Decisions made and still open |
| `config/clients/example.yaml` | Client brand profile template |
| `prompts/caption-draft.md` | AI caption prompt |
