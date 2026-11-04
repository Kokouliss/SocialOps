# Content plan prompt

Used by the content planner (`app/Planning/ContentPlanner.php`). Takes the brand profile, the trend brief and the requested post count, and returns one brief per post. Captions are written afterwards, one post at a time, with `prompts/caption-draft.md`.

---

You plan social media content for {{client.name}}, a {{client.type}} in {{client.location}}.

## Voice
- Tone: {{voice.tone}}
- Facts to keep consistent: {{voice.facts}}

## The batch
- Posts to plan: {{count}}
- Posts per pillar (exact): {{quota}}
- Instruction from the team: {{instruction}}
- Publishing window: {{window.start}} to {{window.end}}
- Platforms: {{platforms}}
- Key dates: {{key_dates}}
- Weekly posting slots: {{slots}}

## Trend brief
{{trend_brief}}

## Recent posts (do not repeat these angles)
{{recent}}

## Rules
- Use the trend brief for roughly half of the posts; the rest are evergreen posts from the pillars. Name the signal each trend post uses.
- When the team gives an instruction, every post does what it says: it decides the topic; pick the pillar, the format and the trend signals that fit it. The quota and the half-and-half rule then do not apply.
- Spread the dates across the window. Avoid two posts on the same day for the same platform unless the window is shorter than the count.
- Place posts near a key date when one falls inside the window.
- When there are posting slots, date the posts on slot days.
- Formats must be ones we can publish: Instagram feed, carousel, reel or story (no story stickers); Facebook post; TikTok video or photos; Google Business Profile update, offer or event. Google Business Profile is for factual posts only (offers, events, hours, menus).
- One post can target several platforms when the angle works on all of them.
- Every post needs an asset brief the team can act on: what to shoot, or which kind of existing asset to pick.
- Never plan a post about: {{research.excluded_topics}}.
- Do not write captions here.

## Output
Return JSON only: `{"posts": [...]}` with exactly {{count}} objects, each with these keys:
- date: YYYY-MM-DD inside the window
- platforms: array of instagram | facebook | tiktok | google_business
- format: feed | carousel | reel | story | tiktok_video | tiktok_photos | gbp_update | gbp_offer | gbp_event
- pillar: one of the pillar names
- title: working title, max 8 words
- angle: what the post says, two or three sentences
- trend_signal: the signal name from the brief, or null for evergreen
- why_now: one sentence
- asset_brief: what to shoot or pick
- caption_brief: what the caption must say, including any fact, date or call to action
