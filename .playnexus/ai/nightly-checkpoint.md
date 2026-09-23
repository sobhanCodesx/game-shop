# PlayNexus AI nightly checkpoint

Updated: 2026-09-23 night window

## Verified source state
- Operational branch: `main`.
- Public PlayNexus AI graph-context integration is implemented in `app/Http/Controllers/Api/NexusAiContextController.php`.
- Retrieval is bounded to at most 2 extracted terms and caches graph context for 1 minute.
- Graph context reads games, studios, products, content, collections and radar; this controller performs no writes.
- Local Ollama/FastAPI worker/model version remains unverified from this repository; do not tune inference parameters until the real active runtime is located.

## Successful iterations
1. `1083f452910a014173d8ca0a681cd8ab239de59b`: improved Latin entity extraction and Persian query-noise filtering.
2. `179f43a08a03d34fc86b0b5fae04899e62f21331`: canonicalized cache-key term ordering so equivalent reversed entity queries reuse Graph context.
3. `5064d8282d8379312aa869bf59022a875480022d`: added the stable 10-scenario gaming eval fixture.
4. `efdb941f1c0e45c06b1ab124030f81a331d3c93c`: replaced raw 6000-character string truncation with structure-aware JSON budgeting. Oversized context now removes lower-priority result rows until each emitted `PLAYNEXUS LIVE CONTEXT` chunk is complete valid JSON; total context remains capped at 6000 characters.

## Benchmark / invariants
- Max extracted terms: 2 -> 2.
- Max cold GraphQL executions: 2 -> 2.
- Warm equivalent reversed-entity request inside TTL: up to 2 extra Graph calls before cache canonicalization -> 0 additional calls after.
- Context cap: 6000 -> 6000 characters.
- Oversized serialized Graph context: could previously be cut mid-JSON -> now only complete JSON chunks are emitted; lower-priority rows are dropped to fit the budget.
- Eval coverage: 10 fixed scenarios retained.
- No inference latency/RAM claim: active Ollama/FastAPI runtime is still not verified.

## Reverted / rejected
- None in this checkpoint.

## Open blockers / risks
- Active local AI worker/model/quantization/runtime still needs verification before inference tuning.
- GraphQL query remains broad for every intent. Intent-aware fast/deep routing is still the next performance hypothesis, but must be benchmarked before shipping.
- Repository has no verified focused automated test for the context controller yet; add one when the test/mocking path for `PlayNexusGraphService` is confirmed.
- Commit `efdb941f...` currently has no registered commit statuses/check runs; GitHub status endpoint reports pending with zero statuses, so CI success is not claimed.

## Next best hypotheses
1. Benchmark and implement a conservative intent-aware Graph fast path for simple game facts while preserving the current deep query for comparisons/fresh-data/product/content questions.
2. Locate and verify the actual Ollama/FastAPI worker/model/quantization; then run the 10-scenario eval with real TTFT/total latency and lightweight resource measurements.
3. Add a focused context-controller test proving emitted context <= 6000 characters and every context chunk contains complete decodable JSON.
4. Evaluate whether duplicate nested game content vs top-level content can be reduced without lowering answer grounding quality.
