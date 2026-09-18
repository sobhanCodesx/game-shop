<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GameRadarService
{
    private const CACHE_KEY = 'playnexus:game-radar:v1';
    private const REFRESHING_KEY = 'playnexus:game-radar:refreshing';
    private const SNAPSHOT_PATH = 'game-radar/snapshot.json';
    private const CACHE_HOURS = 6;
    private const MAX_ITEMS = 24;

    /**
     * Return cache/storage only. This never performs an external request and
     * is safe for the first render of Home/Game Hub.
     *
     * @return array{generated_at:?string, stale:bool, items:array<int, array<string, mixed>>}
     */
    public function cachedSnapshot(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $stored = $this->readStoredSnapshot();
        if ($stored !== null) {
            Cache::put(self::CACHE_KEY, $stored, now()->addHours(self::CACHE_HOURS));

            return $stored;
        }

        return [
            'generated_at' => null,
            'stale' => false,
            'items' => [],
        ];
    }

    public function snapshot(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $stored = $this->readStoredSnapshot();
        if ($stored !== null) {
            Cache::put(self::CACHE_KEY, $stored, now()->addHours(self::CACHE_HOURS));

            return $stored;
        }

        try {
            $snapshot = Cache::lock(self::CACHE_KEY.':warmup', 120)->block(3, function (): array {
                $cached = Cache::get(self::CACHE_KEY);
                if (is_array($cached)) {
                    return $cached;
                }

                $stored = $this->readStoredSnapshot();
                if ($stored !== null) {
                    Cache::put(self::CACHE_KEY, $stored, now()->addHours(self::CACHE_HOURS));

                    return $stored;
                }

                return $this->refresh();
            });

            if (is_array($snapshot)) {
                return $snapshot;
            }
        } catch (Throwable $exception) {
            Log::warning('Game Radar cold-start refresh unavailable', [
                'message' => $exception->getMessage(),
            ]);

            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }

            $stored = $this->readStoredSnapshot();
            if ($stored !== null) {
                $stored['stale'] = true;

                return $stored;
            }
        }

        return [
            'generated_at' => null,
            'stale' => false,
            'items' => [],
        ];
    }

    /**
     * Schedule a cold-cache refresh after the HTTP response has already been
     * sent. This keeps the Game Hub request independent from third-party
     * network latency.
     */
    public function scheduleWarmup(): bool
    {
        if (! Cache::add(self::REFRESHING_KEY, true, now()->addMinutes(2))) {
            return false;
        }

        defer(function (): void {
            try {
                Cache::lock(self::CACHE_KEY.':warmup', 120)->get(function (): void {
                    $cached = $this->cachedSnapshot();

                    if (($cached['items'] ?? []) === []) {
                        $this->refresh();
                    }
                });
            } catch (Throwable $exception) {
                Log::warning('Game Radar deferred warmup failed', [
                    'message' => $exception->getMessage(),
                ]);
            } finally {
                Cache::forget(self::REFRESHING_KEY);
            }
        });

        return true;
    }

    public function isRefreshing(): bool
    {
        return Cache::has(self::REFRESHING_KEY);
    }

    /**
     * Refresh the snapshot from independent public Xbox and PlayStation
     * catalog sources. No API key is required.
     *
     * @return array{generated_at:string, stale:bool, items:array<int, array<string, mixed>>}
     */
    public function refresh(): array
    {
        try {
            $xboxNew = $this->fetchXboxList('Computed/New', 'new');
            $xboxComing = $this->fetchXboxList('Computed/ComingSoon', 'coming');
            $xboxItems = [...$xboxNew, ...$xboxComing];
            $playStationItems = $this->fetchPlayStationLatest();

            $items = $this->mergeRadarSources($xboxItems, $playStationItems);

            if ($items === []) {
                throw new \RuntimeException('Game Radar sources returned no titles.');
            }

            $snapshot = [
                'generated_at' => now()->toISOString(),
                'stale' => false,
                'items' => $items,
            ];

            Storage::disk('local')->put(
                self::SNAPSHOT_PATH,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            );
            Cache::put(self::CACHE_KEY, $snapshot, now()->addHours(self::CACHE_HOURS));

            return $snapshot;
        } catch (Throwable $exception) {
            Log::warning('Game Radar refresh failed', [
                'message' => $exception->getMessage(),
            ]);

            $stale = $this->readStoredSnapshot();
            if ($stale !== null) {
                $stale['stale'] = true;
                Cache::put(self::CACHE_KEY, $stale, now()->addHour());

                return $stale;
            }

            throw $exception;
        }
    }

    /**
     * Load public Xbox/Game Pass lists without requiring an API key.
     *
     * We deliberately avoid the old reco-public.rec.mp.microsoft.com host
     * because it is not reliably resolvable across networks. SIGL lists are
     * small public JSON documents that contain product ids; product details
     * still come from Microsoft's display catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchXboxList(string $list, string $status): array
    {
        $siglIds = match ($status) {
            'new' => [
                // Current Xbox Cloud "Recently added" collection.
                '44a55037-770f-4bbf-bde5-a9fa27dba1da',
                // Public Xbox Cloud / Game Pass "Recently added" fallback.
                'f13cf6b4-57e6-4459-89df-6aec18cf0538',
                // Additional Game Pass Recently Added fallback.
                '3fdd7f57-7092-4b65-bd40-5a9dac1b2b84',
            ],
            'coming' => [
                // Public Game Pass "Coming soon" collection.
                '4165f752-d702-49c8-886b-fb57936f6bae',
            ],
            default => [],
        };

        $ids = collect();

        foreach ($siglIds as $siglId) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(7)
                    ->retry(1, 200)
                    ->get('https://catalog.gamepass.com/sigls/v2', [
                        'id' => $siglId,
                        'market' => 'US',
                        'language' => 'en-US',
                    ]);

                if (! $response->successful()) {
                    Log::warning('Game Radar Xbox SIGL request failed', [
                        'sigl_id' => $siglId,
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                $sourceIds = collect($response->json())
                    ->filter(fn ($entry) => is_array($entry) && filled($entry['id'] ?? null))
                    ->pluck('id')
                    ->filter(fn ($id) => is_string($id) && $id !== '');

                if ($sourceIds->isNotEmpty()) {
                    $ids = $ids->concat($sourceIds);
                    break;
                }
            } catch (Throwable $exception) {
                Log::warning('Game Radar Xbox SIGL source unavailable', [
                    'sigl_id' => $siglId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $ids = $ids->unique()->take(30)->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $products = collect();

        foreach ($ids->chunk(18) as $chunk) {
            try {
                $catalogResponse = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->retry(1, 200)
                    ->get('https://displaycatalog.mp.microsoft.com/v7.0/products', [
                        'market' => 'US',
                        'languages' => 'en-US',
                        'fieldsTemplate' => 'details',
                        'bigIds' => $chunk->implode(','),
                    ]);

                if (! $catalogResponse->successful()) {
                    Log::warning('Game Radar Microsoft display catalog request failed', [
                        'status' => $catalogResponse->status(),
                    ]);

                    continue;
                }

                $products = $products->concat($catalogResponse->json('Products', []));
            } catch (Throwable $exception) {
                Log::warning('Game Radar Microsoft display catalog unavailable', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($products->isEmpty()) {
            return [];
        }

        $rank = $ids->flip();

        return $products
            ->map(fn (array $product) => $this->mapXboxProduct($product, $status))
            ->filter()
            ->sortBy(fn (array $item) => $rank->get($item['id'], PHP_INT_MAX))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapXboxProduct(array $product, string $status): ?array
    {
        $localized = Arr::first((array) data_get($product, 'LocalizedProperties', []));
        if (! is_array($localized)) {
            return null;
        }

        $title = trim((string) ($localized['ProductTitle'] ?? $localized['ShortTitle'] ?? ''));
        $productId = trim((string) ($product['ProductId'] ?? ''));
        if ($title === '' || $productId === '') {
            return null;
        }

        $images = collect((array) ($localized['Images'] ?? []));
        $cover = $this->pickImage($images->all(), ['Poster', 'BoxArt', 'Tile', 'Logo']);
        $banner = $this->pickImage($images->all(), ['SuperHeroArt', 'Hero', 'Screenshot', 'Poster', 'BoxArt']);

        $price = data_get(
            $product,
            'DisplaySkuAvailabilities.0.Availabilities.0.OrderManagementData.Price',
            [],
        );
        $releaseDate = data_get($product, 'MarketProperties.0.OriginalReleaseDate');

        return [
            'id' => $productId,
            'title' => $title,
            'description' => Str::limit(trim((string) ($localized['ShortDescription'] ?? '')), 220),
            'cover_url' => $this->normalizeMediaUrl($cover),
            'banner_url' => $this->normalizeMediaUrl($banner ?: $cover),
            'release_date' => is_string($releaseDate) && $releaseDate !== '' ? $releaseDate : null,
            'status' => $status,
            'developer' => $localized['DeveloperName'] ?? null,
            'publisher' => $localized['PublisherName'] ?? null,
            'xbox' => [
                'available' => true,
                'price' => $this->displayXboxPrice($price),
                'currency' => is_array($price) ? ($price['CurrencyCode'] ?? null) : null,
                'platforms' => ['Xbox'],
                'url' => 'https://www.xbox.com/en-us/games/store/'.Str::slug($title).'/'.$productId,
            ],
            'psn' => [
                'available' => false,
                'price' => null,
                'platforms' => [],
                'url' => null,
            ],
        ];
    }

    /**
     * Read the public PlayStation Store Latest page directly. The page embeds
     * its catalog in __NEXT_DATA__, so no PSN account or API key is required.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlayStationLatest(): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
                ->connectTimeout(3)
                ->timeout(9)
                ->retry(1, 250)
                ->get('https://store.playstation.com/en-us/pages/latest');

            if (! $response->successful()) {
                Log::warning('Game Radar PlayStation Latest request failed', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            if (! preg_match('~<script id="__NEXT_DATA__" type="application/json">(.*?)</script>~s', $response->body(), $matches)) {
                Log::warning('Game Radar PlayStation Latest page did not contain __NEXT_DATA__.');

                return [];
            }

            $payload = json_decode($matches[1], true);
            $apollo = data_get($payload, 'props.apolloState', []);

            if (! is_array($apollo)) {
                return [];
            }

            return collect($apollo)
                ->filter(function ($entity, $key) {
                    if (! is_array($entity)
                        || ! str_starts_with((string) $key, 'Product:')
                        || ($entity['__typename'] ?? null) !== 'Product'
                        || blank($entity['name'] ?? null)
                    ) {
                        return false;
                    }

                    $platforms = array_map(
                        fn ($platform) => strtoupper((string) $platform),
                        (array) ($entity['platforms'] ?? []),
                    );

                    if (! in_array('PS5', $platforms, true)) {
                        return false;
                    }

                    $classification = strtoupper((string) (
                        $entity['localizedStoreDisplayClassification']
                        ?? $entity['storeDisplayClassification']
                        ?? ''
                    ));

                    return ! str_contains($classification, 'ADD-ON')
                        && ! str_contains($classification, 'ADD_ON');
                })
                ->map(fn (array $entity) => $this->mapPlayStationProduct($entity))
                ->filter()
                ->unique(fn (array $item) => $this->normalizeTitle((string) $item['title']))
                ->take(18)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Game Radar PlayStation Latest source unavailable', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPlayStationProduct(array $entity): ?array
    {
        $productId = trim((string) ($entity['id'] ?? ''));
        $title = trim((string) ($entity['name'] ?? ''));

        if ($productId === '' || $title === '') {
            return null;
        }

        $media = collect((array) ($entity['media'] ?? []));
        $master = $media->first(
            fn ($item) => is_array($item)
                && ($item['type'] ?? null) === 'IMAGE'
                && ($item['role'] ?? null) === 'MASTER',
        );
        $background = $media->first(
            fn ($item) => is_array($item)
                && ($item['type'] ?? null) === 'IMAGE'
                && in_array(($item['role'] ?? null), ['BACKGROUND', 'HERO'], true),
        );
        $fallbackImage = $media->first(
            fn ($item) => is_array($item) && ($item['type'] ?? null) === 'IMAGE',
        );

        $cover = is_array($master)
            ? ($master['url'] ?? null)
            : (is_array($fallbackImage) ? ($fallbackImage['url'] ?? null) : null);
        $banner = is_array($background)
            ? ($background['url'] ?? null)
            : $cover;

        $price = is_array($entity['price'] ?? null) ? $entity['price'] : [];
        $platforms = array_values(array_filter(
            (array) ($entity['platforms'] ?? []),
            'is_string',
        ));

        $releaseDate = collect([
            $entity['releaseDate'] ?? null,
            $entity['releaseDateTime'] ?? null,
            $entity['release_date'] ?? null,
        ])->first(fn ($value) => is_string($value) && $value !== '');

        $encoded = strtoupper(json_encode($entity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $coming = str_contains($encoded, 'PRE_ORDER')
            || str_contains($encoded, 'PRE-ORDER')
            || (
                is_string($releaseDate)
                && strtotime($releaseDate) !== false
                && strtotime($releaseDate) > now()->timestamp
            );

        return [
            'id' => 'psn:'.$productId,
            'title' => $title,
            'description' => Str::limit(trim((string) (
                $entity['shortDescription']
                ?? $entity['description']
                ?? ''
            )), 220),
            'cover_url' => $cover,
            'banner_url' => $banner,
            'release_date' => $releaseDate,
            'status' => $coming ? 'coming' : 'new',
            'developer' => $entity['developerName'] ?? null,
            'publisher' => $entity['publisherName'] ?? null,
            'xbox' => [
                'available' => false,
                'price' => null,
                'currency' => null,
                'platforms' => [],
                'url' => null,
            ],
            'psn' => [
                'available' => true,
                'price' => $price['discountedPrice'] ?? $price['basePrice'] ?? null,
                'platforms' => $platforms,
                'url' => 'https://store.playstation.com/en-us/product/'.rawurlencode($productId),
                'image_url' => $cover,
            ],
        ];
    }

    /**
     * Merge independent Xbox and PlayStation feeds while preserving titles
     * that exist on only one console.
     *
     * @param array<int, array<string, mixed>> $xboxItems
     * @param array<int, array<string, mixed>> $playStationItems
     * @return array<int, array<string, mixed>>
     */
    private function mergeRadarSources(array $xboxItems, array $playStationItems): array
    {
        $interleaved = [];
        $max = max(count($xboxItems), count($playStationItems));

        for ($index = 0; $index < $max; $index++) {
            if (isset($playStationItems[$index])) {
                $interleaved[] = $playStationItems[$index];
            }

            if (isset($xboxItems[$index])) {
                $interleaved[] = $xboxItems[$index];
            }
        }

        $merged = [];

        foreach ($interleaved as $item) {
            $key = $this->normalizeTitle((string) ($item['title'] ?? ''));
            if ($key === '') {
                continue;
            }

            if (! isset($merged[$key])) {
                $merged[$key] = $item;
                continue;
            }

            $current = $merged[$key];

            if (($item['xbox']['available'] ?? false) === true) {
                $current['xbox'] = $item['xbox'];
            }

            if (($item['psn']['available'] ?? false) === true) {
                $current['psn'] = $item['psn'];
            }

            foreach (['description', 'cover_url', 'banner_url', 'release_date', 'developer', 'publisher'] as $field) {
                if (blank($current[$field] ?? null) && filled($item[$field] ?? null)) {
                    $current[$field] = $item[$field];
                }
            }

            if (($current['status'] ?? 'new') !== 'coming' && ($item['status'] ?? null) === 'coming') {
                $current['status'] = 'coming';
            }

            $merged[$key] = $current;
        }

        return collect($merged)
            ->values()
            ->take(self::MAX_ITEMS)
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $images
     */
    private function pickImage(array $images, array $purposes): ?string
    {
        foreach ($purposes as $purpose) {
            foreach ($images as $image) {
                if (is_array($image) && ($image['ImagePurpose'] ?? null) === $purpose && filled($image['Uri'] ?? null)) {
                    return (string) $image['Uri'];
                }
            }
        }

        foreach ($images as $image) {
            if (is_array($image) && filled($image['Uri'] ?? null)) {
                return (string) $image['Uri'];
            }
        }

        return null;
    }

    private function normalizeMediaUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return $url;
    }

    private function displayXboxPrice(mixed $price): ?string
    {
        if (! is_array($price)) {
            return null;
        }

        $value = $price['ListPrice'] ?? $price['MSRP'] ?? null;
        if (! is_numeric($value)) {
            return null;
        }

        $currency = (string) ($price['CurrencyCode'] ?? 'USD');
        $amount = number_format((float) $value, 2);

        return $currency === 'USD' ? '$'.$amount : "{$amount} {$currency}";
    }

    private function normalizeTitle(string $value): string
    {
        $value = Str::lower($value);
        $value = str_replace(['™', '®', '©'], '', $value);
        $value = preg_replace('/\b(deluxe|ultimate|standard|premium|complete|edition|bundle|game)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @return array{generated_at:?string, stale:bool, items:array<int, array<string, mixed>>}|null
     */
    private function readStoredSnapshot(): ?array
    {
        if (! Storage::disk('local')->exists(self::SNAPSHOT_PATH)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get(self::SNAPSHOT_PATH), true);

        return is_array($decoded) && isset($decoded['items']) && is_array($decoded['items'])
            ? $decoded
            : null;
    }
}
