# PlayNexus Content Agent MCP

This project exposes a small, token-protected MCP endpoint for PlayNexus content operations.

## Endpoint

`POST /api/mcp`

The endpoint supports MCP tool discovery and tool calls. It serves the modern `2026-07-28` discovery flow and the legacy `2025-11-25` initialize flow.

## Available tools

The MCP now exposes the broader PlayNexus content-admin surface, including search/select/get, safe draft creation, media upload, state transitions, and structured Game Events.

For Nexus Pulse intelligence, these tools are especially important:

- `list_game_events` — read structured Game Events by game, type, or state.
- `upsert_game_event` — create a Game Event as **candidate** or edit an existing event. This tool does not activate a new event.
- `set_game_event_state` — explicitly move a Game Event between `candidate`, `active`, and `dismissed`. Activating requires the same server-side publishing permission used by public content.
- `search_games` — resolve the canonical PlayNexus game before creating an event.
- `create_feed` / `publish_feed` — editorial content remains separate from structured events. Publishing selected news/update/trailer content can automatically create a canonical Game Event.

Canonical Game Event types include release-date changes, releases, major patches, DLC events, subscription changes, price drops, free weekends, major trailers, preload availability, server incidents/restoration, and major news.

Creation remains conservative: editorial content starts as draft, games/studios as inactive, collections as private, and agent-created Game Events as candidate. Binary media is handled only through the dedicated chunked upload tools.

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
- Agent-created Game Events default to `candidate`; activating them uses the publishing kill switch.
- Game Events created automatically from trusted PlayNexus state (published editorial content, release-date changes, and active store discounts) may be activated by the application itself.
- Game Event deduplication is enforced with a unique canonical dedupe key.
- No MCP secret is stored in Git.
