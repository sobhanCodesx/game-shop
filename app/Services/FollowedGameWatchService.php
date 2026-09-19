<?php

namespace App\Services;

use App\Contracts\TrackedGameSourceAdapter;
use App\Models\Game;
use App\Models\GameSourceState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FollowedGameWatchService
{
    /** @var array<int, TrackedGameSourceAdapter> */
    private array $adapters;

    public function __construct(
        private readonly GameRadarService $radar,
        private readonly GameSourceMonitorService $monitor,
        XboxTrackedGameSourceAdapter $xbox,
    ) {
        $this->adapters = [$xbox];
    }

    /**
     * Prime source identity from the local Radar cache only.
     * No external request is made when the user follows a game.
     */
    public function prime(Game $game): array
    {
        $item = $this->radar->storeDataForGame($game);

        if ($item) {
            $this->monitor->observeRadarSnapshot([
                'generated_at' => now()->toISOString(),
                'stale' => false,
                'items' => [$item],
            ]);
        }

        return $this->status($game, true);
    }

    /**
     * Poll each distinct followed game only once, regardless of follower count.
     *
     * @return array{games:int,sources:int,observed:int,changed:int,events:int}
     */
    public function sync(): array
    {
        $gameIds = DB::table('game_subscriptions')
            ->join('games', 'games.id', '=', 'game_subscriptions.game_id')
            ->whereNull('games.deleted_at')
            ->whereIn('games.status', ['active', 'published'])
            ->distinct()
            ->pluck('games.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $stats = [
            'games' => $gameIds->count(),
            'sources' => 0,
            'observed' => 0,
            'changed' => 0,
            'events' => 0,
        ];

        if ($gameIds->isEmpty()) {
            return $stats;
        }

        foreach ($this->adapters as $adapter) {
            $states = GameSourceState::query()
                ->whereIn('game_id', $gameIds->all())
                ->where('source', $adapter->source())
                ->where('scope', 'store')
                ->get();

            if ($states->isEmpty()) {
                continue;
            }

            $stats['sources'] += $states->count();
            $observations = $adapter->fetch($states);
            $result = $this->monitor->observeObservations($observations);

            $stats['observed'] += $result['observed'];
            $stats['changed'] += $result['changed'];
            $stats['events'] += $result['events'];
        }

        return $stats;
    }

    /**
     * @return array{active:bool,mode:string,source_count:int,direct_source_count:int,source_labels:array<int,string>,last_checked_at:?string}
     */
    public function status(Game $game, bool $active): array
    {
        if (! $active) {
            return [
                'active' => false,
                'mode' => 'off',
                'source_count' => 0,
                'direct_source_count' => 0,
                'source_labels' => [],
                'last_checked_at' => null,
            ];
        }

        $states = GameSourceState::query()
            ->where('game_id', $game->id)
            ->get(['source', 'observed_at']);

        $directSources = collect($this->adapters)
            ->pluck('source')
            ->all();

        $labels = $states
            ->pluck('source')
            ->unique()
            ->map(fn (string $source) => $this->sourceLabel($source))
            ->filter()
            ->values();

        return [
            'active' => true,
            'mode' => $states->whereIn('source', $directSources)->isNotEmpty() ? 'direct' : 'smart',
            'source_count' => $states->count(),
            'direct_source_count' => $states->whereIn('source', $directSources)->count(),
            'source_labels' => $labels->all(),
            'last_checked_at' => $states
                ->sortByDesc('observed_at')
                ->first()?->observed_at?->toISOString(),
        ];
    }

    /**
     * @param Collection<int, int>|array<int, int> $gameIds
     * @return array{active_games:int,direct_games:int,source_labels:array<int,string>,last_checked_at:?string}
     */
    public function summaryForGames(Collection|array $gameIds): array
    {
        $ids = collect($gameIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'active_games' => 0,
                'direct_games' => 0,
                'source_labels' => [],
                'last_checked_at' => null,
            ];
        }

        $states = GameSourceState::query()
            ->whereIn('game_id', $ids->all())
            ->get(['game_id', 'source', 'observed_at']);
        $directSources = collect($this->adapters)->pluck('source')->all();

        return [
            'active_games' => $ids->count(),
            'direct_games' => $states
                ->whereIn('source', $directSources)
                ->pluck('game_id')
                ->unique()
                ->count(),
            'source_labels' => $states
                ->pluck('source')
                ->unique()
                ->map(fn (string $source) => $this->sourceLabel($source))
                ->filter()
                ->values()
                ->all(),
            'last_checked_at' => $states->max('observed_at')?->toISOString(),
        ];
    }

    private function sourceLabel(string $source): ?string
    {
        return match ($source) {
            'xbox_store' => 'Xbox Store',
            'playstation_store' => 'PlayStation Store',
            'game_radar_catalog' => 'Official Stores',
            default => null,
        };
    }
}
