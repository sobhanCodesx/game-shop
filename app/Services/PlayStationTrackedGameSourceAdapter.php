<?php

namespace App\Services;

use App\Contracts\TrackedGameSourceAdapter;
use App\Models\GameSourceState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlayStationTrackedGameSourceAdapter implements TrackedGameSourceAdapter
{
    private const URL = 'https://web.np.playstation.com/api/graphql/v1/op';
    private const PRODUCT_HASH = 'a128042177bd93dd831164103d53b73ef790d56f51dae647064cb8f9d9fc9d1a';

    public function source(): string
    {
        return 'playstation_store';
    }

    public function supports(GameSourceState $state): bool
    {
        return $this->productId($state) !== null;
    }

    public function fetch(Collection $states): array
    {
        $observations = [];

        foreach ($states as $state) {
            if (! $state instanceof GameSourceState) {
                continue;
            }

            $productId = $this->productId($state);
            if (! $productId) {
                // Concept-only entries do not have a stable product identity yet.
                continue;
            }

            try {
                $variables = ['productId' => $productId];
                $extensions = [
                    'persistedQuery' => [
                        'version' => 1,
                        'sha256Hash' => self::PRODUCT_HASH,
                    ],
                ];

                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-psn-store-locale-override' => 'es-cr',
                ])
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->retry(1, 200)
                    ->get(self::URL, [
                        'operationName' => 'metGetProductById',
                        'variables' => json_encode($variables, JSON_UNESCAPED_SLASHES),
                        'extensions' => json_encode($extensions, JSON_UNESCAPED_SLASHES),
                    ]);

                if (! $response->successful() || filled($response->json('errors'))) {
                    Log::warning('Nexus Watch PlayStation source request failed', [
                        'status' => $response->status(),
                        'product_id' => $productId,
                    ]);

                    continue;
                }

                $product = $response->json('data.productRetrieve');
                if (! is_array($product)) {
                    continue;
                }

                $releaseDate = $this->dateValue($product['releaseDate'] ?? null);
                $previous = is_array($state->state) ? $state->state : [];

                $observations[] = [
                    'game_id' => $state->game_id,
                    'source' => $this->source(),
                    'source_label' => 'PlayStation Store',
                    'scope' => 'store',
                    'external_id' => 'psn-product:'.$productId,
                    'source_url' => $state->source_url,
                    'confidence' => 0.99,
                    'watch_direct' => true,
                    'state' => [
                        ...$previous,
                        'available' => true,
                        'release_date' => $releaseDate ?? ($previous['release_date'] ?? null),
                        'release_phase' => $releaseDate
                            ? (now()->startOfDay()->lt(Carbon::parse($releaseDate)) ? 'coming' : 'released')
                            : ($previous['release_phase'] ?? null),
                        'platforms' => array_values(array_filter(
                            (array) ($product['platforms'] ?? $previous['platforms'] ?? []),
                            'is_string',
                        )),
                        'title' => $product['name'] ?? ($previous['title'] ?? null),
                    ],
                ];
            } catch (Throwable $exception) {
                Log::warning('Nexus Watch PlayStation source unavailable', [
                    'product_id' => $productId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $observations;
    }

    private function productId(GameSourceState $state): ?string
    {
        if (is_string($state->external_id) && str_starts_with($state->external_id, 'psn-product:')) {
            $value = trim(substr($state->external_id, 12));

            return $value !== '' ? $value : null;
        }

        if (! $state->source_url || ! str_contains($state->source_url, '/product/')) {
            return null;
        }

        $path = parse_url($state->source_url, PHP_URL_PATH);
        $value = is_string($path) ? rawurldecode(trim((string) basename($path))) : '';

        return $value !== '' ? $value : null;
    }

    private function dateValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
