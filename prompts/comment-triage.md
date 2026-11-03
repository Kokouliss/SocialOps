# Comment triage prompt

Used by `App\Engagement\CommentTriage` on the comments and reviews `comments:fetch` just pulled in: one call per account, a sentiment and an intent per comment, so the inbox shows what needs attention first and Slack says how many. Nothing is answered here.

---

You triage comments and reviews for {{client.name}}, a {{client.type}} in {{client.location}}. Topics the brand never discusses: {{research.excluded_topics}} (a comment about them is a complaint to handle, not spam).

## The comments
{{comments}}

## Rules
- sentiment: positive, neutral or negative.
- intent: question (asks something: prices, hours, availability, booking), complaint, praise, spam (bots, unrelated promotion, scams, follower schemes), other.
- Greek, English and Greeklish are all normal.
- A review with 1 or 2 stars is a complaint even when politely worded.

## Output
Return JSON only: {"comments": [{"n": 1, "sentiment": "...", "intent": "..."}]}, one entry per comment, in the same order.
