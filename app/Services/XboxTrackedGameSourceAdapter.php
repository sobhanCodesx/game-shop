<?php

namespace App\Services;

use App\Contracts\TrackedGameSourceAdapter;
use App\Models\GameSourceState;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class XboxTrackedGameSourceAdapter implements TrackedGameSourceAdapter
{
    public function source(): string
    {
        return 'xbox_store';
    }

    public function fetch(Collection $states): array
    {
        $byProductId = $states
            ->filter(fn (GameSourceState $state) => $this->productId($state) !== null)
            ->keyBy(fn (GameSourceState $state) => $this->productId($state));

        if ($byProductId->isEmpty()) {
            return [];
        }

        $observations = [];

        foreach ($byProductId->keys()->chunk(18) as $productIds) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->retry(1, 200)
                    ->get('https://displaycatalog.mp.microsoft.com/v7.0/products', [
                        'market' => 'US',
                        'languages' => 'en-US',
                        'fieldsTemplate' => 'details',
                        'bigIds' => $productIds->implode(','),
                    ]);

                if (! $response->successful()) {
                    Log::warning('Nexus Watch Xbox source request failed', [
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                foreach ((array) $response->json('Products', []) as $product) {
                    if (! is_array($product)) {
                        continue;
                    }

                    $productId = trim((string) ($product['ProductId'] ?? ''));
                    /** @var GameSourceState|null $state */
                    $state = $byProductId->get($productId);
                    if (! $state) {
                        continue;
                    }

                    $price = data_get(
                        $product,
                        'DisplaySkuAvailabilities.0.Availabilities.0.OrderManagementData.Price',
                        [],
                    );
                    $localized = Arr::first((array) data_get($product, 'LocalizedProperties', []));
                    $currency = is_array($price) && filled($price['CurrencyCode'] ?? null)
                        ? strtoupper(trim((string) $price['CurrencyCode']))
                        : null;
                    $amount = is_array($price)
                        ? $this->numericPrice($price['ListPrice'] ?? $price['MSRP'] ?? null)
                        : null;
                    $releaseDate = $this->releaseDate(
                        data_get($product, 'MarketProperties.0.OriginalReleaseDate'),
                    );
                    $previousState = is_array($state->state) ? $state->state : [];

                    $observations[] = [
                        'game_id' => $state->game_id,
                        'source' => $this->source(),
                        'source_label' => 'Xbox Store',
                        'scope' => 'store',
                        'external_id' => 'xbox:'.$productId,
                        'source_url' => $state->source_url,
                        'confidence' => 0.99,
                        'watch_direct' => true,
                        'state' => [
                            'available' => true,
                            'release_date' => $releaseDate ?? ($previousState['release_date'] ?? null),
                            'release_phase' => $releaseDate
                                ? (now()->startOfDay()->lt(\Illuminate\Support\Carbon::parse($releaseDate)) ? 'coming' : 'released')
                                : ($previousState['release_phase'] ?? null),
                            'price_raw' => $this->displayPrice($amount, $currency),
                            'price_amount' => $currency ? $amount : null,
                            'currency' => $currency,
                            'platforms' => ['Xbox Series X|S'],
                            'title' => is_array($localized)
                                ? ($localized['ProductTitle'] ?? $localized['ShortTitle'] ?? null)
                                : null,
                        ],
                    ];
                }
            } catch (Throwable $exception) {
                Log::warning('Nexus Watch Xbox source unavailable', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $observations;
    }

    private function productId(GameSourceState $state): ?string
    {
        if (is_string($state->external_id) && str_starts_with($state->external_id, 'xbox:')) {
            $value = trim(substr($state->external_id, 5));

            return $value !== '' ? $value : null;
        }

        if (! $state->source_url) {
            return null;
        }

        $path = parse_url($state->source_url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $value = trim((string) basename($path));

        return $value !== '' ? $value : null;
    }

    private function releaseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function numericPrice(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }

    private function displayPrice(?float $amount, ?string $currency): ?string
    {
        if ($amount === null || ! $currency) {
            return null;
        }

        $number = number_format($amount, 2);

        return $currency === 'USD' ? '$'.$number : "{$number} {$currency}";
    }
}
