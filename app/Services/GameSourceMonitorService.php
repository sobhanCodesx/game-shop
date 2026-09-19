<?php

namespace App\Services;

use App\Contracts\GameSourceAdapter;
use App\Models\GameSourceState;
use Illuminate\Support\Facades\DB;

class GameSourceMonitorService
{
    public function __construct(
        private readonly RadarGameSourceAdapter $radar,
        private readonly GameSourceChangeDetector $detector,
    ) {}

    /**
     * Observe the already-fetched, locally-linked Game Radar snapshot.
     * The first observation establishes a baseline and never emits an event.
     *
     * @return array{observed:int,baselines:int,changed:int,events:int}
     */
    public function observeRadarSnapshot(array $snapshot): array
    {
        return $this->observe($this->radar, $snapshot);
    }

    /**
     * @return array{observed:int,baselines:int,changed:int,events:int}
     */
    private function observe(GameSourceAdapter $adapter, array $payload): array
    {
        $stats = [
            'observed' => 0,
            'baselines' => 0,
            'changed' => 0,
            'events' => 0,
        ];

        foreach ($adapter->observations($payload) as $observation) {
            DB::transaction(function () use ($observation, &$stats): void {
                $state = is_array($observation['state'] ?? null) ? $observation['state'] : [];
                $fingerprint = $this->fingerprint($state);
                $now = now();

                $previous = GameSourceState::query()
                    ->where('game_id', (int) $observation['game_id'])
                    ->where('source', (string) $observation['source'])
                    ->where('scope', (string) $observation['scope'])
                    ->lockForUpdate()
                    ->first();

                $stats['observed']++;

                if (! $previous) {
                    GameSourceState::query()->create([
                        'game_id' => (int) $observation['game_id'],
                        'source' => (string) $observation['source'],
                        'scope' => (string) $observation['scope'],
                        'external_id' => $observation['external_id'] ?? null,
                        'source_url' => $observation['source_url'] ?? null,
                        'confidence' => (float) ($observation['confidence'] ?? 1),
                        'fingerprint' => $fingerprint,
                        'state' => $state,
                        'observed_at' => $now,
                        'changed_at' => null,
                    ]);
                    $stats['baselines']++;

                    return;
                }

                if (hash_equals($previous->fingerprint, $fingerprint)) {
                    $previous->fill([
                        'external_id' => $observation['external_id'] ?? $previous->external_id,
                        'source_url' => $observation['source_url'] ?? $previous->source_url,
                        'confidence' => (float) ($observation['confidence'] ?? $previous->confidence),
                        'observed_at' => $now,
                    ])->save();

                    return;
                }

                $events = $this->detector->detect($previous, $observation);
                $stats['changed']++;
                $stats['events'] += count($events);

                $previous->fill([
                    'external_id' => $observation['external_id'] ?? $previous->external_id,
                    'source_url' => $observation['source_url'] ?? $previous->source_url,
                    'confidence' => (float) ($observation['confidence'] ?? $previous->confidence),
                    'fingerprint' => $fingerprint,
                    'state' => $state,
                    'observed_at' => $now,
                    'changed_at' => $now,
                ])->save();
            }, 3);
        }

        return $stats;
    }

    private function fingerprint(array $state): string
    {
        $normalized = $this->normalize($state);

        return hash('sha256', json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private function normalize(array $state): array
    {
        foreach ($state as $key => $value) {
            if (is_array($value)) {
                $state[$key] = array_is_list($value)
                    ? array_values($value)
                    : $this->normalize($value);
            }
        }

        ksort($state);

        return $state;
    }
}
