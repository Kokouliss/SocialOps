---
name: social-ops-claude-connector
description: How Claude works inside Social Ops — the OAuth MCP connector and its tools, the Claude job queue, and the hourly "Social Ops: queued Claude jobs" scheduled task. Read before a Claude-jobs run or any connector problem.
sources: [cowork]
aliases: [Social Ops connector, Social Ops MCP, queued Claude jobs, Claude queue, do the Claude jobs]
---
## Connector
- [stated] Add in claude.ai: Settings → Connectors → Add custom connector, name "Social Ops", URL https://socialops.inglelandi.com/mcp → Connect → log in → Allow (OAuth Client ID left empty)
- [stated] Built in #106 (Laravel Passport 13 + Mcp::oauthRoutes(); ScopedToToken limits a login to its client_ids); confirmed working on Panos's account 2026-11-04
- [stated] Tools: list-clients, list-posts, get-post, create-draft, update-post, get-caption-brief, get-planning-brief, save-plan, list-claude-jobs
- [stated] Planning from chat: get-planning-brief → trend research (last30days approach) → save-plan; captions: get-caption-brief → update-post
- [stated] A login only sees its own clients: the old routine's login saw only "Inglelandi Digital Agency" (id 2), not Fuse Pharmacies
- [stated] A missing new tool means the server was not deployed (Pull now + /refresh) or the connector's tool list is stale (remove and re-add the connector)
- [stated] In-app Claude buttons need the Anthropic key and FEATURE_CLAUDE_API=true; with the key removed a button gives 401, so set the flag false and use the chat

## Queue
- [stated] "Ask Claude for the captions" tick on the post form (settings.ask_claude) and "Queue for Claude" on the client planning box ("Waiting for Claude" in Reports) queue jobs (#108)
- [stated] save-plan with the job id, and update-post with ask_claude=false, clear the jobs
- [stated] Writing "do the Claude jobs" in the project chat runs the queue at once

## Scheduled task "Social Ops: queued Claude jobs"
- [stated] Hourly, fresh thread per run, push notification; Panos wanted every 5 then every 10 minutes, but scheduled tasks have a 1-hour minimum
- [stated] Steps: list-claude-jobs; each caption job get-caption-brief → update-post (settings.ask_claude false); each plan job get-planning-brief → web research → save-plan with the job id; report in Greek with the links; no jobs → stop
- [stated] On Panos's account it ran at :56 past each hour and worked end to end from 2026-11-04 (queue read, empty); it must be recreated on the new account after the connector is added there
- [stated] A missing list-claude-jobs means deploy/reconnect, not an empty queue: report that step in Greek
