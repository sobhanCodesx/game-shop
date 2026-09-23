# PlayNexus AI nightly checkpoint

Updated: 2026-09-22 night window

## Verified source state
- Operational branch: `main`.
- Public PlayNexus AI graph-context integration is implemented in `app/Http/Controllers/Api/NexusAiContextController.php`.
- Current retrieval is bounded to at most 2 extracted terms and caches graph context for 1 minute.
- Graph context query currently reads games, studios, products, content, collections and radar; writes are not performed by this controller.
- Local Ollama/FastAPI worker/model version is not verified from this repository in this run. Do not tune model/runtime parameters until the real active service is located.

## Successful iterations
1. `1083f452910a014173d8ca0a681cd8ab239de59b`: improved Latin entity extraction and Persian query-noise filtering; retrieval remains capped at 2 terms.
2. `179f43a08a03d34fc86b0b5fae04899e62f21331`: canonicalized cache-key term ordering. Questions resolving to the same two entities in opposite order can reuse cached Graph context instead of duplicating GraphQL work; response term order is unchanged.
3. `5064d8282d8379312aa869bf59022a875480022d`: added a stable 10-scenario gaming eval fixture covering open-world facts, preference comparison, hardware, fresh updates, spoiler safety, time/value, similar-game recommendation, real ambiguity, PlayNexus Graph grounding and rumor separation.

## Benchmark / invariants
- Max extracted terms: before 2 / after 2.
- Max GraphQL executions on a cold context request: before 2 / after 2.
- Warm equivalent-entity request with reversed term order inside TTL: before could miss cache and execute up to 2 GraphQL calls / after reuses canonical cache key and executes 0 additional GraphQL calls.
- Context hard cap remains 6000 characters.
- Eval coverage: before no stable repo fixture found / after 10 fixed scenarios with explicit expected and forbidden behaviors.
- No inference latency/RAM claim is recorded because the active Ollama/FastAPI runtime was not verified in this run.

## Reverted / rejected
- None in this checkpoint.

## Open blockers / risks
- Active local AI worker/model/quantization/runtime still needs verification before inference tuning.
- Current final `Str::limit(..., 6000)` can truncate serialized JSON context mid-structure. Replace it with structure-aware budgeting so the model never receives malformed/truncated JSON.
- Current GraphQL query is broad for every intent. A benchmarked fast-path/deep-path split is a high-value next step, but must preserve grounding coverage.

## Next best hypotheses
1. Implement structure-aware context budgeting and focused tests proving complete/valid context under the size budget.
2. Add a lightweight evaluator around `tests/Fixtures/nexus_ai_eval.json` once the real AI endpoint/worker is verified, recording factuality/relevance/usefulness/tone/completeness and latency.
3. Benchmark intent-aware Graph query routing (simple game fact fast path vs comparison/deep path) before shipping it.
4. Locate and verify the actual active Ollama/FastAPI worker, model and quantization; only then benchmark TTFT/total latency and lightweight inference options.
