# PlayNexus Content Agent MCP

This project exposes a small, token-protected MCP endpoint for PlayNexus content operations.

## Endpoint

`POST /api/mcp`

The endpoint supports MCP tool discovery and tool calls. It serves the modern `2026-07-28` discovery flow and the legacy `2025-11-25` initialize flow.

## Available tools

- `search_games` — find an existing PlayNexus game.
- `search_studios` — find an existing active studio.
- `get_feed` — read a feed post, including drafts.
- `create_feed` — create a feed post. It always creates a **draft**.
- `update_feed` — edit a feed post without publishing it.
- `publish_feed` — publish a feed post only when server-side publishing is explicitly enabled.

No tool can delete users, orders, settings, products, games, studios, or feed posts.

## Production configuration

Add these values to the production `.env` file. Never commit the real token.

```dotenv
PLAYNEXUS_CONTENT_AGENT_TOKEN=<long-random-secret>
PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID=<existing-admin-user-id>
PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH=false
```

Generate a strong token on the server:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

`PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID` must point to an existing user whose `is_admin` value is true. AI-created drafts are attributed to that user.

Keep `PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH=false` while testing. Set it to `true` only when direct publishing is desired.

After changing `.env`, clear/rebuild Laravel configuration as appropriate for the deployment:

```bash
php artisan optimize:clear
php artisan config:cache
```

## Smoke test

Replace `TOKEN` with the production token:

```bash
curl -X POST "https://playnexus.ir/api/mcp" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

The response should contain the PlayNexus content tools.

## Security model

- Static Bearer token checked with `hash_equals`.
- API rate limit: 60 requests per minute.
- Content author is pinned to one configured admin user.
- `create_feed` always creates a draft.
- Publishing has a second server-side kill switch.
- Feed HTML passes through the existing `RichText` sanitizer.
- Existing database validation is reused for game/product/video relationships.
- No MCP secret is stored in Git.
