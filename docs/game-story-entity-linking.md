# Game Story Phase II: verified internal-link graph

Editorial invariant: Game Story, physical products and digital products are associated through the same persisted games.id. Never infer a game_id from names, descriptions, translated titles, tags, categories or studio affiliation.

## Public link rules
- Story page: link to its exact game channel, the game's active studio if available, and publicly visible physical and digital products with equal game_id.
- Digital or physical product: link back to published stories only for its exact game_id.
- Active studio page: link to published stories belonging to the studio's own games.
- Hide draft content, unpublished products and inactive studios. Missing mappings produce no product cards; fix data explicitly rather than guessing.
- Game channel remains the principal entity hub. Keep the existing channel, studio, story, and product relations.

## SEO and SSR
- Render real clickable anchors in the initial Inertia SSR HTML and use canonical routes and contextual anchor text.
- Schema relatedLink/about/creator references must reflect real page links and active game/studio data.
- Keep the original manuscript and inline figures unchanged; crosslinks belong in the separately styled ecosystem section.
- Never inject redundant keyword-rich links into prose or show a price unless current pricing is available.

## Verification
- tests/Feature/GameStoryTest.php covers matching game IDs, published-only products, active studios, unpublished stories and reciprocal links.
- Also run PlayNexus Game Story Check, Digital Commerce Check, Game Intelligence Check and Storefront Cache Phases Check.
- Production deployment is only complete when the secure HTTPS deploy workflow and post-deploy health checks succeed.
