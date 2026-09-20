# PlayNexus Native Mobile API v1

This API is the stable backend contract for the native PlayNexus mobile app. It lives beside the existing Inertia web application and does not replace the PlayNexus MCP or read-only Intelligence GraphQL endpoints.

## Base URL

Production:

```text
https://playnexus.ir/api/v1
```

All authenticated mobile requests use:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

Tokens are first-party PlayNexus mobile tokens. Only a SHA-256 hash is stored in the database. The default lifetime is 180 days and can be configured with `MOBILE_API_TOKEN_TTL_DAYS`.

## Server setup

After deploying the branch:

```bash
php artisan migrate --force
php artisan optimize:clear
```

For native Google Sign-In configure the audience ids used by the Android/iOS apps:

```env
GOOGLE_ANDROID_CLIENT_ID=
GOOGLE_IOS_CLIENT_ID=
```

The existing `GOOGLE_CLIENT_ID` remains valid for the web OAuth client.

Expired mobile tokens are pruned daily by `nexus:prune-mobile-api-tokens`. The normal Laravel scheduler must already be running, as it is for the existing PlayNexus scheduled jobs.

## Authentication

### Guest endpoints

- `POST /auth/register`
- `POST /auth/verify`
- `POST /auth/verification/resend`
- `POST /auth/login`
- `POST /auth/passwordless/request`
- `POST /auth/passwordless/verify`
- `POST /auth/password/reset/request`
- `POST /auth/password/reset/confirm`
- `POST /auth/google`

A successful login/verification returns:

```json
{
  "token_type": "Bearer",
  "access_token": "...",
  "expires_at": "2027-03-19T10:00:00.000000Z",
  "user": {}
}
```

The app should store the token in platform secure storage (Keychain/Keystore), not AsyncStorage.

### Authenticated session endpoints

- `POST /auth/logout`
- `POST /auth/logout-all`

## Public/mobile-personalized content

These routes also accept an optional Bearer token. When present, fields such as `is_liked`, `is_saved`, `is_subscribed`, personalized Home data, and user pricing can be returned.

- `GET /meta`
- `GET /home`
- `GET /feed`
- `GET /feed/trending`
- `GET /discover`
- `GET /videos`
- `GET /shorts`
- `GET /contents/{slug}`
- `GET /contents/{slug}/comments`
- `POST /contents/{slug}/views`

The Home contract includes configurable Home settings, slides, category navigation, featured/latest products, editorial feed, studios, Game Radar, configurable content sections, fresh content, channels, and authenticated personalization signals.

## Store and discovery

- `GET /products`
- `GET /products/{slug}`
- `GET /categories`
- `GET /categories/{slug}`
- `GET /search?q=...`
- `GET /search/suggestions?q=...`
- `GET /game-radar`

Product list query parameters include `q`, `category`, `trade`, `offers`, `sort`, and `per_page`. Supported sort values are `latest`, `popular`, `price_asc`, and `price_desc`.

## Games, studios and collections

- `GET /studios`
- `GET /studios/{slug}`
- `GET /channels/{gameSlug}`
- `GET /channels/{gameSlug}/playlists/{playlistSlug}`
- `GET /collections/{playlistSlug}`

Channel payloads include the game identity, studio, platforms, subscriber/video counts, subscription state, playlists, videos, editorial feed, watch intelligence and Game Radar store data.

## Community

Authentication is required for mutations:

- `POST /contents/{slug}/reaction`
- `POST /contents/{slug}/save`
- `POST /contents/{slug}/comments`
- `POST /comments/{id}/like`
- `DELETE /comments/{id}`
- `POST /channels/{gameSlug}/subscription`

Posts accept `like`. Videos/shorts accept `like` and `dislike`.

## Account

- `GET /me`
- `PATCH /me`
- `PUT /me/password`
- `GET /addresses`
- `POST /addresses`
- `PUT /addresses/{id}`
- `DELETE /addresses/{id}`
- `GET /notifications`
- `PATCH /notifications/read-all`
- `PATCH /notifications/{id}`
- `GET /notification-preferences`
- `PUT /notification-preferences`
- `GET /saved`
- `GET /orders`

Profile avatar updates use multipart form data.

## Native cart and checkout

The native app owns its local cart state. The API never relies on the web session/cart cookie.

Send cart items in this shape:

```json
{
  "items": [
    {
      "product_id": 42,
      "variant_id": null,
      "quantity": 1
    }
  ]
}
```

Server endpoints:

- `POST /cart/resolve` — canonical server price, stock and summary
- `POST /checkout/bootstrap` — addresses, wallet, exchange credits and current totals
- `POST /checkout/preview` — coupon/wallet/exchange preview
- `POST /checkout` — atomic order creation
- `GET /orders/{id}`
- `GET /orders/{id}/invoice`
- `PATCH /orders/{id}/cancel`

Never trust locally cached prices in the app. Always call `/cart/resolve` or checkout preview before presenting final payment totals.

## Tickets and exchanges

- `GET /tickets`
- `GET /tickets/create-context`
- `POST /tickets`
- `GET /tickets/{id}`
- `POST /tickets/{id}/replies`
- `PATCH /tickets/{id}/exchange-response`

The web-only anti-bot session challenge is intentionally not used by the native API because these routes require a valid Bearer token and are rate limited.

## Push devices

- `GET /devices`
- `PUT /devices`
- `DELETE /devices/{installationId}`

The existing `MobileDevice`/FCM infrastructure is reused. Register the real native FCM token after authentication and whenever the token rotates.

## Watch progress

- `GET /watch-progress`
- `PUT /watch-progress/{contentId}`
- `DELETE /watch-progress/{contentId}`

Progress is server-side per user and works across devices.

## Rate limiting and trust boundaries

Public reads, auth attempts, comments, reactions and other sensitive operations have explicit throttles in addition to Laravel's API middleware.

The native app is considered an untrusted client:

- prices, stock, wallet balances, coupons and exchange credits are recalculated on the server;
- ownership checks are enforced on addresses, orders, tickets, notifications and comments;
- authentication tokens are hashed at rest;
- password changes/reset invalidate appropriate mobile sessions;
- content visibility and publication state are always checked server-side.

## Compatibility

The existing PlayNexus endpoints remain separate and unchanged:

- `POST /api/graphql` — read-only Intelligence Graph
- `POST /api/mcp` — operational content agent

The native API is versioned under `/api/v1` so future mobile releases can introduce a `v2` contract without breaking installed app versions.
