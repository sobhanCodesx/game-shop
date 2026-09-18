<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
    private const SNAPSHOT_PATH = 'game-radar/snapshot.json';
    private const CACHE_HOURS = 6;
    private const MAX_ITEMS = 18;

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
            report($exception);

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
     * Refresh the snapshot from the public Xbox catalog and enrich each title
     * with a best-effort PlayStation Store lookup. No API key is required.
     *
     * @return array{generated_at:string, stale:bool, items:array<int, array<string, mixed>>}
     */
    public function refresh(): array
    {
        try {
            $new = $this->fetchXboxList('Computed/New', 'new');
            $coming = $this->fetchXboxList('Computed/ComingSoon', 'coming');

            $items = collect([...$new, ...$coming])
                ->unique('id')
                ->take(self::MAX_ITEMS)
                ->values()
                ->all();

            $items = $this->enrichWithPlayStation($items);

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
            report($exception);

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
                // Xbox Cloud / Game Pass "Recently added".
                'f13cf6b4-57e6-4459-89df-6aec18cf0538',
                // Additional currently-used Recently Added collection.
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
                    ->timeout(10)
                    ->retry(1, 250)
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
                    ->timeout(12)
                    ->retry(1, 250)
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
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithPlayStation(array $items): array
    {
        if ($items === []) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($items) {
                return array_map(
                    fn (array $item, int $index) => $pool
                        ->as((string) $index)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                            'Accept-Language' => 'en-US,en;q=0.9',
                        ])
                        ->timeout(12)
                        ->get('https://store.playstation.com/en-us/search/'.rawurlencode($item['title'])),
                    $items,
                    array_keys($items),
                );
            });
        } catch (Throwable $exception) {
            report($exception);

            return $items;
        }

        foreach ($items as $index => &$item) {
            $response = $responses[(string) $index] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $match = $this->parsePlayStationSearch($response->body(), (string) $item['title']);
            if ($match !== null) {
                $item['psn'] = $match;
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePlayStationSearch(string $html, string $title): ?array
    {
        if (! preg_match('~<script id="__NEXT_DATA__" type="application/json">(.*?)</script>~s', $html, $matches)) {
            return null;
        }

        $payload = json_decode($matches[1], true);
        $apollo = data_get($payload, 'props.apolloState', []);
        if (! is_array($apollo)) {
            return null;
        }

        $needle = $this->normalizeTitle($title);
        $best = null;
        $bestScore = 0.0;

        foreach ($apollo as $key => $entity) {
            if (! is_array($entity)
                || ! str_starts_with((string) $key, 'Product:')
                || ($entity['__typename'] ?? null) !== 'Product'
                || blank($entity['name'] ?? null)) {
                continue;
            }

            $candidate = $this->normalizeTitle((string) $entity['name']);
            if ($candidate === '') {
                continue;
            }

            similar_text($needle, $candidate, $score);
            if ($needle === $candidate) {
                $score = 100;
            } elseif (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                $score = max($score, 88);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entity;
            }
        }

        if (! is_array($best) || $bestScore < 74) {
            return null;
        }

        $productId = trim((string) ($best['id'] ?? ''));
        if ($productId === '') {
            return null;
        }

        $price = is_array($best['price'] ?? null) ? $best['price'] : [];
        $media = collect((array) ($best['media'] ?? []));
        $image = $media->first(
            fn ($item) => is_array($item) && ($item['type'] ?? null) === 'IMAGE' && ($item['role'] ?? null) === 'MASTER',
        ) ?? $media->first(fn ($item) => is_array($item) && ($item['type'] ?? null) === 'IMAGE');

        return [
            'available' => true,
            'price' => $price['discountedPrice'] ?? $price['basePrice'] ?? null,
            'platforms' => array_values(array_filter((array) ($best['platforms'] ?? []), 'is_string')),
            'url' => 'https://store.playstation.com/en-us/product/'.rawurlencode($productId),
            'image_url' => is_array($image) ? ($image['url'] ?? null) : null,
        ];
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
