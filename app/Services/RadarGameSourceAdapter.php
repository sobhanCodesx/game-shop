<?php

namespace App\Services;

use App\Contracts\GameSourceAdapter;

class RadarGameSourceAdapter implements GameSourceAdapter
{
    public function observations(array $payload): array
    {
        $observations = [];

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $gameId = (int) ($item['playnexus_game_id'] ?? 0);
            if ($gameId < 1) {
                continue;
            }

            $evidenceUrls = collect(['psn', 'xbox'])
                ->map(fn (string $store) => data_get($item, "{$store}.url"))
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->values()
                ->all();

            $observations[] = [
                'game_id' => $gameId,
                'source' => 'game_radar_catalog',
                'source_label' => 'PlayNexus Radar / Official Stores',
                'scope' => 'catalog',
                'external_id' => 'radar:game:'.$gameId,
                'source_url' => $evidenceUrls[0] ?? null,
                'confidence' => 0.96,
                'state' => [
                    'status' => in_array(($item['status'] ?? null), ['coming', 'new'], true)
                        ? $item['status']
                        : null,
                    'release_date' => $this->dateValue($item['release_date'] ?? null),
                    'evidence_urls' => $evidenceUrls,
                ],
            ];

            foreach ([
                'psn' => ['source' => 'playstation_store', 'label' => 'PlayStation Store'],
                'xbox' => ['source' => 'xbox_store', 'label' => 'Xbox Store'],
            ] as $key => $meta) {
                $store = is_array($item[$key] ?? null) ? $item[$key] : [];
                if (($store['available'] ?? false) !== true) {
                    continue;
                }

                $currency = filled($store['currency'] ?? null)
                    ? strtoupper(trim((string) $store['currency']))
                    : null;
                $priceRaw = filled($store['price'] ?? null)
                    ? trim((string) $store['price'])
                    : null;

                $observations[] = [
                    'game_id' => $gameId,
                    'source' => $meta['source'],
                    'source_label' => $meta['label'],
                    'scope' => 'store',
                    'external_id' => $this->externalId($meta['source'], $store['url'] ?? null, $gameId),
                    'source_url' => is_string($store['url'] ?? null) ? $store['url'] : null,
                    'confidence' => 0.98,
                    'state' => [
                        'available' => true,
                        'release_date' => $this->dateValue($item['release_date'] ?? null),
                        'release_phase' => ($item['status'] ?? null) === 'coming' ? 'coming' : 'released',
                        'price_raw' => $priceRaw,
                        'price_amount' => $currency ? $this->priceAmount($priceRaw) : null,
                        'currency' => $currency,
                        'platforms' => array_values(array_filter(
                            (array) ($store['platforms'] ?? []),
                            'is_string',
                        )),
                    ],
                ];
            }
        }

        return $observations;
    }

    private function dateValue(mixed $value): ?string
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

    private function priceAmount(?string $value): ?float
    {
        if (! $value) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', $value);
        if (! preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches)) {
            return null;
        }

        $amount = (float) $matches[0];

        return $amount >= 0 ? $amount : null;
    }

    private function externalId(string $source, mixed $url, int $gameId): string
    {
        if (is_string($url) && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path)) {
                $value = trim((string) basename($path));

                if ($value !== '') {
                    if ($source === 'xbox_store') {
                        return 'xbox:'.$value;
                    }

                    if ($source === 'playstation_store') {
                        $type = str_contains($path, '/concept/') ? 'concept' : 'product';

                        return 'psn-'.$type.':'.rawurldecode($value);
                    }
                }
            }
        }

        return $source.':game:'.$gameId;
    }
}
