# PlayNexus AI Content & Intelligence MCP

PlayNexus exposes a private, token-protected MCP endpoint plus a read-only GraphQL intelligence endpoint. The design deliberately separates **reasoning** from **actions**:

- GraphQL is the AI's read/discovery layer.
- MCP action tools are the only supported write layer.
- Existing publisher queue workflows and scheduled jobs continue to use the same MCP contract.

## Endpoints

### MCP

`POST /api/mcp`

Supports MCP tool discovery/calls, modern `2026-07-28` discovery and the legacy `2025-11-25` initialize flow.

### Intelligence Graph

`POST /api/graphql`

Uses the exact same Bearer token as MCP. It accepts GraphQL **query** operations only. Mutations and subscriptions are rejected before execution.

There is no public GraphiQL/IDE route.

## Recommended AI workflow

1. Use `describe_playnexus_graph` when the schema or examples are not already known.
2. Use `query_playnexus_graph` to gather the smallest useful relational context in one request.
3. Decide what, if anything, should change.
4. Use the existing dedicated MCP action tool for that change.
5. Use explicit publish/state tools only when the user has authorized the public transition.

This allows the model to explore deeply without exposing raw SQL or unrestricted writes.

## Intelligence Graph

The graph includes:

- Games
- Studios
- Platforms
- Products and Brands
- Editorial content (feeds, videos, stories and shorts), including attached media and aggregate engagement
- Collections/playlists
- Store categories
- Cached Game Radar data
- Nexus Pulse `GameEvent` records when that subsystem is installed
- Nexus Watch/source-state records when that subsystem is installed

Game Events and Source States are first-class graph entities on the Nexus Pulse branch. The repository layer still guards their tables so the Graph remains deployment-order safe during migrations.

### Example: understand a game in one request

```graphql
query GameContext($slug: String!) {
  game(slug: $slug) {
    id
    name
    releaseDate
    studio {
      id
      name
    }
    platforms {
      id
      name
    }
    products(first: 5) {
      nodes {
        id
        title
        price
        discountPrice
        status
      }
      pageInfo {
        total
        hasMore
      }
    }
    content(first: 8, orderBy: "published_at") {
      nodes {
        id
        type
        title
        feedBadge
        status
        publishedAt
      }
      pageInfo {
        total
        hasMore
        nextOffset
      }
    }
    events(first: 8, minImportance: 60) {
      nodes {
        id
        type
        title
        importanceScore
        sourceName
        detectedAt
      }
      pageInfo {
        total
      }
    }
  }
}
```

### Example: cross-entity discovery

```graphql
query SearchPlayNexus($q: String!) {
  search(query: $q, first: 6) {
    games {
      id
      name
      status
      releaseDate
    }
    studios {
      id
      name
    }
    content {
      id
      type
      title
      status
      publishedAt
    }
    products {
      id
      title
      status
      price
    }
  }
}
```

### Example: direct HTTP call

```bash
curl -X POST "https://playnexus.ir/api/graphql" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"query":"{ graphInfo { name version readOnly entities maxDepth maxComplexity } }"}'
```

## Broad private read profile

The production defaults intentionally give the authenticated PlayNexus agent a large read budget. It can traverse deep entity relationships, inspect drafts/inactive records when explicitly filtered, read full editorial bodies, attached media metadata, Brands, aggregate content engagement, Radar, Game Events and Nexus Watch source states.

This does **not** expose raw users, watch histories, email addresses, passwords/hashes, sessions, tokens, environment variables, arbitrary SQL, filesystem access or secrets. The larger read surface remains a typed application graph rather than unrestricted database access.

## Graph safety model

The Graph is intentionally read-only and bounded:

- Same static Bearer-token authentication as MCP.
- Separate API throttle.
- Mutations/subscriptions rejected before execution.
- Maximum GraphQL document size.
- Maximum variables payload size.
- Maximum response size.
- Maximum selection depth.
- Separate bounded introspection depth.
- Maximum field count.
- Query complexity budget.
- Hard page-size and offset caps.
- Heavy fields such as article bodies and JSON source state cost more complexity points.
- Graph query logs contain hash/metrics, not the raw query text.
- Radar resolvers read the existing linked cache and do not trigger external Store requests.
- No raw SQL, arbitrary filesystem access, secrets, shell or code execution.

Each response contains an `extensions.playnexus` block with the query hash, depth, complexity, field count and execution time.

## MCP intelligence tools

### `describe_playnexus_graph`

Returns:

- Complete GraphQL SDL
- Safety limits
- Usage guidance
- Ready-to-use example queries

### `query_playnexus_graph`

Arguments:

- `query` — GraphQL query
- `variables` — optional variables object
- `operation_name` — optional operation name

Use it for relational discovery. Do not use many old search/get calls when one graph query can gather the required context.

## Structured Game Event actions

When Nexus Pulse is installed, the MCP also exposes controlled structured-event actions:

- `list_game_events` — read Game Events by game, type or moderation state.
- `upsert_game_event` — create a new Event as **candidate** or edit an existing Event.
- `set_game_event_state` — move an Event between `candidate`, `active` and `dismissed`; activation uses the existing publishing permission.

GraphQL is the preferred read path for relational Event context, while these tools remain the controlled write/moderation path. Agent-created Events never bypass moderation by becoming active automatically.

## MCP action layer

Existing actions remain available and keep their safety semantics, including:

- search/select/get compatibility tools
- create game/studio/collection/story/video/feed
- update content/feed
- collection-video synchronization
- explicit content state transitions
- explicit feed publish/unpublish
- media upload lifecycle
- guarded delete/restore operations

Creation remains conservative:

- feeds/stories/videos → draft
- games/studios → inactive
- collections → private

GraphQL does not bypass any of those rules.

## Production configuration

Never commit the real token.

```dotenv
PLAYNEXUS_CONTENT_AGENT_TOKEN=<long-random-secret>
PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID=<existing-admin-user-id>
PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH=false
PLAYNEXUS_CONTENT_AGENT_ALLOW_DESTRUCTIVE=false
PLAYNEXUS_CONTENT_AGENT_ALLOW_UPLOADS=false

PLAYNEXUS_CONTENT_AGENT_GRAPHQL_ENABLED=true
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_INTROSPECTION=true
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_QUERY_BYTES=48000
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_VARIABLES_BYTES=96000
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_RESPONSE_BYTES=4194304
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_DEPTH=14
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_INTROSPECTION_DEPTH=20
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_COMPLEXITY=1500
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_FIELDS=600
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_PAGE_SIZE=100
PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_OFFSET=50000
```

Generate a token on the server:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

After changing environment values:

```bash
php artisan optimize:clear
php artisan config:cache
```

## MCP smoke test

```bash
curl -X POST "https://playnexus.ir/api/mcp" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

The response should include `describe_playnexus_graph` and `query_playnexus_graph` alongside all existing action tools.

## Deployment

This feature has no database migration of its own.

After code deployment:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
```

The Composer lock includes `webonyx/graphql-php` and should be deployed with `composer.json`.
