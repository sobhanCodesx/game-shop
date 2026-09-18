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
    private const CACHE_KEY = 'playnexus:game-radar:v2';
    private const SNAPSHOT_PATH = 'game-radar/snapshot-v2.json';
    private const CACHE_HOURS = 6;
    private const MAX_ITEMS = 30;

    private const PSN_GRAPHQL_URL = 'https://web.np.playstation.com/api/graphql/v1/op';
    private const PSN_CATEGORY_GRID_HASH = '88c0b9a1273c6d320c51cd73e390924e21ae28bf09f01cde8b84b1034b16cd03';
    private const PS5_CATEGORY = 'd71e8e6d-0940-4e03-bd02-404fc7d31a31';
    private const PS5_COMING_SOON_CATEGORY = '82ced94c-ed3f-4d81-9b50-4d4cf1da170b';

    /**
     * Cache/storage only. Never performs an external HTTP request.
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

    /**
     * The only method that talks to Xbox/PlayStation. Call it from the
     * scheduled Artisan job or the protected admin maintenance action.
     *
     * @return array{generated_at:string, stale:bool, items:array<int, array<string, mixed>>}
     */
    public function refresh(): array
    {
        try {
            $playStationItems = $this->fetchPlayStationCatalog();
            $xboxNew = $this->fetchXboxList('new');
            $xboxComing = $this->fetchXboxList('coming');
            $xboxItems = [...$xboxNew, ...$xboxComing];

            if ($playStationItems === []) {
                throw new \RuntimeException('PlayStation 5 source returned no titles.');
            }

            if ($xboxItems === []) {
                throw new \RuntimeException('Xbox source returned no titles.');
            }

            $items = $this->mergeRadarSources(
                $xboxItems,
                $playStationItems,
            );

            $snapshot = [
                'generated_at' => now()->toISOString(),
                'stale' => false,
                'items' => $items,
            ];

            Storage::disk('local')->put(
                self::SNAPSHOT_PATH,
                json_encode(
                    $snapshot,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
                ),
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
     * @return array<int, array<string, mixed>>
     */
    private function fetchXboxList(string $status): array
    {
        $siglIds = match ($status) {
            'new' => [
                '44a55037-770f-4bbf-bde5-a9fa27dba1da',
                'f13cf6b4-57e6-4459-89df-6aec18cf0538',
                '3fdd7f57-7092-4b65-bd40-5a9dac1b2b84',
            ],
            'coming' => [
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
                    continue;
                }

                $sourceIds = collect($response->json())
                    ->filter(fn ($entry) => is_array($entry) && filled($entry['id'] ?? null))
                    ->pluck('id')
                    ->filter(fn ($id) => is_string($id) && $id !== '');

                if ($sourceIds->isNotEmpty()) {
                    $ids = $sourceIds;
                    break;
                }
            } catch (Throwable $exception) {
                Log::warning('Game Radar Xbox SIGL source unavailable', [
                    'sigl_id' => $siglId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $ids = $ids->unique()->take(24)->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $products = collect();

        foreach ($ids->chunk(18) as $chunk) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->retry(1, 200)
                    ->get('https://displaycatalog.mp.microsoft.com/v7.0/products', [
                        'market' => 'US',
                        'languages' => 'en-US',
                        'fieldsTemplate' => 'details',
                        'bigIds' => $chunk->implode(','),
                    ]);

                if ($response->successful()) {
                    $products = $products->concat($response->json('Products', []));
                }
            } catch (Throwable $exception) {
                Log::warning('Game Radar Microsoft display catalog unavailable', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $rank = $ids->flip();

        return $products
            ->map(fn (array $product) => $this->mapXboxProduct($product, $status))
            ->filter()
            ->sortBy(fn (array $item) => $rank->get($item['id'], PHP_INT_MAX))
            ->take(16)
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

        $images = (array) ($localized['Images'] ?? []);
        $cover = $this->pickXboxImage($images, ['Poster', 'BoxArt', 'Tile', 'Logo']);
        $banner = $this->pickXboxImage($images, ['SuperHeroArt', 'Hero', 'Screenshot', 'Poster', 'BoxArt']);
        $price = data_get(
            $product,
            'DisplaySkuAvailabilities.0.Availabilities.0.OrderManagementData.Price',
            [],
        );
        $releaseDate = data_get($product, 'MarketProperties.0.OriginalReleaseDate');

        return [
            'id' => 'xbox:'.$productId,
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
                'platforms' => ['Xbox Series X|S'],
                'url' => 'https://www.xbox.com/en-us/games/store/'.Str::slug($title).'/'.$productId,
            ],
            'psn' => $this->emptyStore(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlayStationCatalog(): array
    {
        $new = $this->fetchPlayStationGrid(
            self::PS5_CATEGORY,
            'new',
            'products',
            18,
        );

        $coming = $this->fetchPlayStationGrid(
            self::PS5_COMING_SOON_CATEGORY,
            'coming',
            'concepts',
            10,
        );

        return collect([...$coming, ...$new])
            ->filter(fn (array $item) => ($item['psn']['available'] ?? false) === true)
            ->unique(fn (array $item) => $this->normalizeTitle((string) $item['title']))
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlayStationGrid(
        string $categoryId,
        string $status,
        string $collection,
        int $limit,
    ): array {
        try {
            $variables = [
                'id' => $categoryId,
                'pageArgs' => [
                    'size' => min(24, $limit),
                    'offset' => 0,
                ],
                'sortBy' => null,
                'filterBy' => [],
                'facetOptions' => [],
            ];

            $extensions = [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => self::PSN_CATEGORY_GRID_HASH,
                ],
            ];

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Language' => 'es-CR,es;q=0.9,en;q=0.8',
                'Referer' => 'https://store.playstation.com/es-cr/category/'.$categoryId.'/1',
                'Origin' => 'https://store.playstation.com',
                'apollo-require-preflight' => 'true',
                'x-apollo-operation-name' => 'categoryGridRetrieve',
            ])
                ->connectTimeout(3)
                ->timeout(9)
                ->retry(1, 250)
                ->get(self::PSN_GRAPHQL_URL, [
                    'operationName' => 'categoryGridRetrieve',
                    'variables' => json_encode($variables, JSON_UNESCAPED_SLASHES),
                    'extensions' => json_encode($extensions, JSON_UNESCAPED_SLASHES),
                ]);

            if (! $response->successful()) {
                Log::warning('Game Radar PlayStation GraphQL request failed', [
                    'category' => $categoryId,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $payload = $response->json();

            if (! is_array($payload) || filled($payload['errors'] ?? null)) {
                Log::warning('Game Radar PlayStation GraphQL returned errors', [
                    'category' => $categoryId,
                    'errors' => $payload['errors'] ?? null,
                ]);

                return [];
            }

            $nodes = data_get($payload, 'data.categoryGridRetrieve.'.$collection, []);

            if (! is_array($nodes)) {
                return [];
            }

            return collect($nodes)
                ->map(fn (array $node) => $collection === 'concepts'
                    ? $this->mapPlayStationConcept($node, $status)
                    : $this->mapPlayStationProduct($node, $status))
                ->filter()
                ->take($limit)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Game Radar PlayStation GraphQL source unavailable', [
                'category' => $categoryId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPlayStationProduct(array $product, string $status): ?array
    {
        $productId = trim((string) ($product['id'] ?? ''));
        $title = trim((string) ($product['name'] ?? ''));

        if ($productId === '' || $title === '') {
            return null;
        }

        $classification = strtoupper((string) ($product['storeDisplayClassification'] ?? ''));
        if (
            str_contains($classification, 'ADD_ON')
            || str_contains($classification, 'ADD-ON')
            || str_contains($classification, 'SOUNDTRACK')
            || str_contains($classification, 'DEMO')
        ) {
            return null;
        }

        $platforms = array_values(array_filter(
            (array) ($product['platforms'] ?? []),
            'is_string',
        ));

        if ($platforms !== [] && ! in_array('PS5', array_map('strtoupper', $platforms), true)) {
            return null;
        }

        $cover = $this->pickPlayStationImage((array) ($product['media'] ?? []));
        $price = is_array($product['price'] ?? null) ? $product['price'] : [];

        return [
            'id' => 'psn:'.$productId,
            'title' => $title,
            'description' => null,
            'cover_url' => $cover,
            'banner_url' => $cover,
            'release_date' => null,
            'status' => $status,
            'developer' => null,
            'publisher' => null,
            'xbox' => $this->emptyStore(),
            'psn' => [
                'available' => true,
                'price' => $price['discountedPrice'] ?? $price['basePrice'] ?? null,
                'currency' => null,
                'platforms' => $platforms !== [] ? $platforms : ['PS5'],
                'url' => 'https://store.playstation.com/es-cr/product/'.rawurlencode($productId),
                'image_url' => $cover,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPlayStationConcept(array $concept, string $status): ?array
    {
        $conceptId = trim((string) ($concept['id'] ?? ''));
        $title = trim((string) ($concept['name'] ?? ''));

        if ($conceptId === '' || $title === '') {
            return null;
        }

        $productIds = collect((array) ($concept['products'] ?? []))
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();

        $hasPs5 = $productIds->contains(fn ($id) => str_contains(strtoupper((string) $id), 'PPSA'));

        if ($productIds->isNotEmpty() && ! $hasPs5) {
            return null;
        }

        $cover = $this->pickPlayStationImage((array) ($concept['media'] ?? []));
        $price = is_array($concept['price'] ?? null) ? $concept['price'] : [];
        $productId = $productIds->first();

        return [
            'id' => 'psn:concept:'.$conceptId,
            'title' => $title,
            'description' => null,
            'cover_url' => $cover,
            'banner_url' => $cover,
            'release_date' => null,
            'status' => $status,
            'developer' => null,
            'publisher' => null,
            'xbox' => $this->emptyStore(),
            'psn' => [
                'available' => true,
                'price' => $price['discountedPrice'] ?? $price['basePrice'] ?? null,
                'currency' => null,
                'platforms' => ['PS5'],
                'url' => $productId
                    ? 'https://store.playstation.com/es-cr/product/'.rawurlencode((string) $productId)
                    : 'https://store.playstation.com/es-cr/concept/'.rawurlencode($conceptId),
                'image_url' => $cover,
            ],
        ];
    }

    /**
     * Keep PS5 titles first in the stored snapshot. UI can then render
     * independent PS5 and Xbox shelves without doing any network work.
     *
     * @param array<int, array<string, mixed>> $xboxItems
     * @param array<int, array<string, mixed>> $playStationItems
     * @return array<int, array<string, mixed>>
     */
    private function mergeRadarSources(array $xboxItems, array $playStationItems): array
    {
        $merged = [];

        foreach ([...$playStationItems, ...$xboxItems] as $item) {
            $key = $this->normalizeTitle((string) ($item['title'] ?? ''));
            if ($key === '') {
                continue;
            }

            if (! isset($merged[$key])) {
                $merged[$key] = $item;
                continue;
            }

            $current = $merged[$key];

            if (($item['psn']['available'] ?? false) === true) {
                $current['psn'] = $item['psn'];
            }

            if (($item['xbox']['available'] ?? false) === true) {
                $current['xbox'] = $item['xbox'];
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
    private function pickXboxImage(array $images, array $purposes): ?string
    {
        foreach ($purposes as $purpose) {
            foreach ($images as $image) {
                if (
                    is_array($image)
                    && ($image['ImagePurpose'] ?? null) === $purpose
                    && filled($image['Uri'] ?? null)
                ) {
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

    /**
     * @param array<int, array<string, mixed>> $media
     */
    private function pickPlayStationImage(array $media): ?string
    {
        foreach (['MASTER', 'GAMEHUB_COVER_ART', 'PORTRAIT', 'KEY_ART'] as $role) {
            foreach ($media as $item) {
                if (
                    is_array($item)
                    && ($item['role'] ?? null) === $role
                    && filled($item['url'] ?? null)
                ) {
                    return (string) $item['url'];
                }
            }
        }

        foreach ($media as $item) {
            if (is_array($item) && filled($item['url'] ?? null)) {
                return (string) $item['url'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStore(): array
    {
        return [
            'available' => false,
            'price' => null,
            'currency' => null,
            'platforms' => [],
            'url' => null,
            'image_url' => null,
        ];
    }

    private function normalizeMediaUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return str_starts_with($url, '//') ? 'https:'.$url : $url;
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
