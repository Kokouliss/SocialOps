# Decisions

## Decided
| # | Decision | Why | Date |
|---|---|---|---|
| A1 | Build our own system rather than buy a SaaS tool | User's call: build, but copy what the commercial tools do best | 2026-11-03 |
| A2 | Multi-client from day one (per-client isolation) | Several clients now, more coming | 2026-11-03 |
| A3 | Cut time across the whole pipeline, not one step | User's call | 2026-11-03 |
| A4 | Own the workflow, rent publishing through an adapter | Platform approvals take weeks to months; adapter allows a direct Meta driver later | 2026-11-03 (research recommendation) |
| A5 | MVP platforms: Instagram, Facebook, Google Business Profile; TikTok in Phase 2 | Highest value for hospitality; TikTok needs an audit first | 2026-11-03 (research recommendation) |

A4 and A5 come from the research recommendation. Confirm them with the user at kickoff.

## Open (ask the user before building on these)
| # | Question | Options | Research leaning |
|---|---|---|---|
| D1 | App stack | (a) Custom Laravel app; (b) custom Next.js app; (c) extend Mixpost Pro ($299) | (a) Laravel matches the team's PHP/WordPress skills; (c) is the cheapest feature-complete base but needs our own app reviews |
| D2 | Publishing layer | Zernio (per-account, cheapest) / Ayrshare (most complete, $149+/mo) / Mixpost's own drivers | Zernio for cost; Ayrshare if analytics, comments and reviews are wanted from one API |
| D3 | Hosting | Own VPS (EU) / managed platform (EU region) | EU region either way for GDPR |
| D4 | Which clients and platforms go first | List current clients and their active platforms | Pilot with 1 client, then roll out |
| D5 | Languages beyond EL/EN | German, French, Italian for some villas? | AI-drafted, spot-checked |
| D6 | Client portal branding | Agency brand or white-label per client | White-label later (Phase 2) |
