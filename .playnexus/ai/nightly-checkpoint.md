# PlayNexus AI nightly checkpoint

Updated: 2026-09-22 night window

## Verified source state
- Operational branch: `main`.
- Public PlayNexus AI graph-context integration is implemented in `app/Http/Controllers/Api/NexusAiContextController.php`.
- Current retrieval is bounded to at most 2 extracted terms and caches graph context for 1 minute.
- Graph context query currently reads games, studios, products, content, collections and radar; writes are not performed by this controller.
- Local Ollama/FastAPI worker/model version is not verified from this repository in this run. Do not tune model/runtime parameters until the real active service is located.

## Successful iterations
1. Commit `1083f452910a014173d8ca0a681cd8ab239de59b`: improved Latin entity extraction and Persian query-noise filtering. Retrieval remains capped at 2 terms.
2. Commit `179f43a08a03d34fc86b0b5fae04899e62f21331`: canonicalized cache-key term ordering. Questions resolving to the same two entities in opposite order can now reuse the same cached Graph context instead of duplicating GraphQL work. Response term order is unchanged.

## Benchmark / invariants
- Max extracted terms: before 2 / after 2.
- Max GraphQL executions on a cold context request: before 2 / after 2.
- Warm equivalent-entity request with reversed term order inside TTL: before could miss cache and execute up to 2 GraphQL calls / after reuses canonical cache key and executes 0 additional GraphQL calls.
- Context hard cap remains 6000 characters.
- No inference latency/RAM claim is recorded because the active Ollama/FastAPI runtime was not verified in this run.

## Reverted / rejected
- None in this checkpoint.

## Open blockers / risks
- Active local AI worker/model/quantization/runtime still needs verification before inference tuning.
- Current final `Str::limit(..., 6000)` can truncate serialized JSON context mid-structure. This should be replaced with structure-aware budgeting so the model never receives malformed/truncated JSON.
- Current GraphQL query is broad for every intent. A benchmarked fast-path/deep-path split is a high-value next step, but should preserve grounding coverage.

## Next best hypotheses
1. Implement structure-aware context budgeting and add focused tests proving valid complete context under the size budget.
2. Add a small stable Nexus AI eval fixture covering open-world, comparison, hardware, update/news, spoiler-safe story, value, similar-game recommendation, ambiguity, PlayNexus context and rumor handling.
3. Benchmark intent-aware Graph query routing (simple game fact fast path vs comparison/deep path) before shipping it.
4. Locate and verify the actual active Ollama/FastAPI worker, model and quantization; only then benchmark TTFT/total latency and lightweight inference options.
