# Decisions

## Decided
| # | Decision | Why | Date |
|---|---|---|---|
| A1 | Build our own system rather than buy a SaaS tool | User's call: build, but copy what the commercial tools do best | 2026-11-03 |
| A2 | Multi-client from day one (per-client isolation) | Several clients now, more coming | 2026-11-03 |
| A3 | Cut time across the whole pipeline, not one step | User's call | 2026-11-03 |
| A4 | Own the workflow and the publishing: our own platform apps and drivers behind a `Publisher` adapter; no paid aggregator | User's call at kickoff (replaces the research recommendation to rent publishing); the adapter keeps each platform a separate driver | 2026-11-03 |
| A5 | Platforms: Instagram, Facebook, TikTok and Google Business Profile, all four in the MVP; no other platform, now or later | User's call (replaces the research recommendation of TikTok in Phase 2); TikTok posts are private-only until our audit passes | 2026-11-03 |
| D1 | App stack: custom Laravel app | Matches the team's PHP/WordPress skills; publishing stays behind an adapter | 2026-11-03 |
| D2 | Publishing layer: direct drivers built in-house (Meta Graph API, TikTok Content Posting API, Google Business Profile API); no paid service | User's call: no paid third-party services (Claude API and hosting are fine). The cost is calendar time: Phase 0 approvals become the critical path | 2026-11-03 |


## Open (ask the user before building on these)
| # | Question | Options | Research leaning |
|---|---|---|---|
| D3 | Hosting | Own VPS (EU) / managed platform (EU region) | EU region either way for GDPR |
| D4 | Which clients and platforms go first | List current clients and their active platforms | Pilot with 1 client, then roll out |
| D5 | Languages beyond EL/EN | German, French, Italian for some villas? | AI-drafted, spot-checked |
| D6 | Client portal branding | Agency brand or white-label per client | White-label later (Phase 2) |
