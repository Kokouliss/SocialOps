# JSON API, MCP server and webhooks

For scripts and other tools (a reporting sheet, Zapier-style automations the agency runs itself). JSON in, JSON out. Everything a token can do, a staff member can do in the app; the API never publishes a post, approval stays with a human.

## Tokens

Staff page → "API tokens" → name it, optionally limit it to one client → the token is shown once. Send it on every call:

```
Authorization: Bearer so_…
Accept: application/json
```

120 calls a minute per token. A token limited to one client gets 403 on any other client's data. Revoke on the Staff page.

## Endpoints

| Method | Path | What |
|---|---|---|
| GET | `/api/clients` | The clients the token may see: id, slug, code, name, type, location |
| GET | `/api/clients/{id}/posts?from=&to=&state=` | Posts (up to 500), oldest first; `from`/`to` are dates on `scheduled_at`, `state` one of idea, draft, in_review, changes_requested, approved, scheduled, published, failed, manual_needed, withdrawn |
| POST | `/api/clients/{id}/posts` | A new Draft, same fields and rules as the post form (below) |
| GET | `/api/posts/{id}` | One post |
| GET | `/api/clients/{id}/comments?open=1` | The inbox: comments, direct messages and Google reviews; `open=0` for everything |
| POST | `/api/comments/{id}/reply` | `{"text": "…"}` sends the reply on the platform and marks the comment answered |

### Creating a Draft

```json
{
  "platforms": ["instagram", "facebook"],
  "format": "feed",
  "pillar": "food",
  "brief": "New summer menu, photo of the sea bass",
  "scheduled_at": "2027-05-12 18:00",
  "settings": {"first_comment": "#chania"},
  "variants": {
    "instagram": {"el": {"caption": "…", "alt_text": "…", "hashtags": ["#chania", "#crete"]}, "en": {"caption": "…"}},
    "facebook": {"el": {"caption": "…"}}
  },
  "assets": [12, 15]
}
```

`platforms` and `format` are required; `pillar` must be one of the profile's pillars; `assets` are ids of the client's own assets, in order. The Draft is named by the usual convention and goes through review and approval in the app like any other.

### A post as the API returns it

```json
{"id": 42, "name": "ALMYRA_20270512_IG-FB_FEED_FOOD_EL-EN_v1", "client_id": 1, "state": "draft", "platforms": ["instagram", "facebook"], "format": "feed", "pillar": "food", "brief": "…", "scheduled_at": "2027-05-12T18:00:00+03:00", "published_at": null, "external_ids": null, "publish_error": null, "metrics": null, "settings": {"first_comment": "#chania"}, "captions": {"instagram": {"el": {"caption": "…", "alt_text": "…", "hashtags": ["#chania", "#crete"]}}}, "assets": [{"id": 12, "name": "ALMYRA_20270510_ANY_IMG_FOOD_EL-EN_v1.jpg", "kind": "image"}], "url": "https://…/posts/42/edit"}
```

Errors come as JSON: 401 without a valid token, 403 for another client's data, 422 with `errors` per field when the input is wrong, 422 with `message` when a platform refuses a reply.

## Webhooks

In the client profile:

```yaml
webhooks:
  - url: https://example.com/socialops
    secret: shared-secret          # optional
    events: [post.published]       # optional, default all three
```

Events: `post.published`, `post.failed`, `post.manual_needed`. Each is a POST with a JSON body `{"event": "post.published", "at": "2027-05-12T18:00:04+03:00", "post": {…as above…}}` and two headers: `X-SocialOps-Event` and `X-SocialOps-Signature`, the hex HMAC-SHA256 of the raw body with the secret (empty secret, empty key). Check the signature before trusting the body. A delivery that does not answer 2xx is retried after one minute and again after ten.

## MCP server

The same reads and writes as the API, as tools for Claude Code, Claude Desktop or any MCP client, so you can say "draft three posts for Almyra for next week" or "what did Blue Villa do in October" and let Claude call them. Nothing here publishes; a Draft waits for review and approval in the app.

Endpoint: `POST https://<host>/mcp` (Streamable HTTP), the same bearer token as the API, the same client scope and rate limit.

```
# Claude Code
claude mcp add --transport http socialops https://<host>/mcp --header "Authorization: Bearer so_…"

# Claude Desktop (Settings → Developer → Edit Config), through the free mcp-remote bridge
{"mcpServers": {"socialops": {"command": "npx", "args": ["-y", "mcp-remote", "https://<host>/mcp", "--header", "Authorization: Bearer so_…"]}}}

# On the server itself, over stdio, every client in reach
php artisan mcp:start socialops
```

Tools: `list-clients`, `list-posts` (client, from, to, state), `get-post` (post), `create-draft` (client, platforms, format, pillar, brief, scheduled_at, variants, settings, assets: the fields of the post form above), `update-post` (post, plus any of those fields: only what is sent changes, a caption per platform and language replaces that one, an approved post goes back to In Review, a published one is refused), `list-comments` (client, open), `reply-to-comment` (comment, text), `month-numbers` (client, month YYYY-MM). Validation errors come back as tool errors with the field messages.
