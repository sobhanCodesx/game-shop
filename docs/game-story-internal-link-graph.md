# Game Story internal-link graph (phase II)

This feature joins existing **published Game Story** content with first-class PlayNexus entities. It intentionally does **not** infer relationships from overlapping game titles or text.

## Grounded relations

- `GameStory.game_id` → `Game.id` → the canonical game channel.
- `Game.studio_id` → `Studio.id` → a public studio page, only if the studio is active.
- `Product.game_id` → `Game.id` → physical products, only when `publiclyVisible()`.
- `DigitalProduct.game_id` → `Game.id` → digital products, only when `published()`.
- Reverse links on products are populated by `GameStory::published()` for **the exact same `game_id`**.
- Studio pages link to published stories only from their associated active/published games.

The server owns the links in `GameStoryLinkGraphService`; the Inertia reader renders ordinary `Link` anchors in the HTML. Search engines and readers can follow the same real links. SSR and SEO metadata are preserved; no paid packages or migrations are involved.

## Edge cases

An unassigned digital product (`game_id = null`) is not automatically linked. Staff must link it to the correct game in the administration panel. Inactive studios, draft stories, non-public physical products, and unpublished digital products must never leak through this graph.

## Verification

`tests/Feature/GameStoryTest.php` contains same-game, unpublished, inactive-studio and reverse-page checks. Keep these tests green when changing the graph. The general full backend suite contains known baseline issues and is tracked separately from the Game Story, Digital Commerce, Storefront Cache and Game Intelligence CI checks.

## Production rollout

The feature is surfaced in the Game Story reading room and on studio, physical product and digital product pages. The deployment job verifies the installed revision and production health; a successful GitHub merge alone does not mean it is live.
