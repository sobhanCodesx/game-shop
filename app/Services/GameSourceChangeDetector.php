<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSourceState;

class GameSourceChangeDetector
{
    public function __construct(
        private readonly GameEventService $events,
        private readonly GameEventImportanceService $importance,
    ) {}

    /**
     * @return array<int, GameEvent>
     */
    public function detect(GameSourceState $previous, array $observation): array
    {
        $game = $previous->game;
        if (! $game || ! in_array($game->status, ['active', 'published'], true)) {
            return [];
        }

        $old = is_array($previous->state) ? $previous->state : [];
        $new = is_array($observation['state'] ?? null) ? $observation['state'] : [];

        return match ($previous->scope) {
            'catalog' => $this->catalogChanges($game, $previous, $old, $new, $observation),
            'store' => $this->storeChanges($game, $previous, $old, $new, $observation),
            default => [],
        };
    }

    /**
     * @return array<int, GameEvent>
     */
    private function catalogChanges(
        Game $game,
        GameSourceState $previous,
        array $old,
        array $new,
        array $observation,
    ): array {
        $events = [];
        $oldDate = $old['release_date'] ?? null;
        $newDate = $new['release_date'] ?? null;

        $releasedNow = ($old['status'] ?? null) === 'coming'
            && ($new['status'] ?? null) === 'new';

        if (! $releasedNow && $oldDate && $newDate && $oldDate !== $newDate) {
            $this->expireLiveSourceEvent($game->id, 'release_date_changed', $previous->external_id);

            $events[] = $this->events->upsert([
                'game_id' => $game->id,
                'type' => 'release_date_changed',
                'title' => "تاریخ انتشار {$game->name} در منبع رسمی تغییر کرد",
                'summary' => 'Radar یک تغییر قابل‌اثبات در تاریخ انتشار ثبت‌شده توسط منبع فروشگاهی پیدا کرده.',
                'source_type' => 'external_catalog',
                'source_name' => $observation['source_label'] ?? 'Official Store',
                'source_url' => $observation['source_url'] ?? null,
                'external_id' => $previous->external_id,
                'dedupe_key' => "source:{$previous->source}:game:{$game->id}:release-date:{$oldDate}:{$newDate}",
                'importance_score' => $this->importance->defaultScore('release_date_changed'),
                'confidence' => $observation['confidence'] ?? $previous->confidence,
                'old_value' => ['release_date' => $oldDate],
                'new_value' => ['release_date' => $newDate],
                'metadata' => [
                    'source' => $previous->source,
                    'evidence_urls' => $new['evidence_urls'] ?? [],
                ],
                'effective_at' => now(),
                'status' => 'active',
            ]);
        }

        if ($releasedNow) {
            $events[] = $this->events->upsert([
                'game_id' => $game->id,
                'type' => 'released',
                'title' => "{$game->name} حالا منتشر شده",
                'summary' => 'Radar وضعیت این بازی را از «در راه» به «منتشرشده» تغییر داده.',
                'source_type' => 'external_catalog',
                'source_name' => $observation['source_label'] ?? 'Official Store',
                'source_url' => $observation['source_url'] ?? null,
                'external_id' => $previous->external_id,
                'dedupe_key' => "source:{$previous->source}:game:{$game->id}:released",
                'importance_score' => $this->importance->defaultScore('released'),
                'confidence' => $observation['confidence'] ?? $previous->confidence,
                'old_value' => ['status' => 'coming'],
                'new_value' => ['status' => 'new'],
                'metadata' => [
                    'source' => $previous->source,
                    'evidence_urls' => $new['evidence_urls'] ?? [],
                ],
                'effective_at' => now(),
                'status' => 'active',
            ]);
        }

        return $events;
    }

    /**
     * @return array<int, GameEvent>
     */
    private function storeChanges(
        Game $game,
        GameSourceState $previous,
        array $old,
        array $new,
        array $observation,
    ): array {
        $oldCurrency = $old['currency'] ?? null;
        $newCurrency = $new['currency'] ?? null;
        $oldPrice = $old['price_amount'] ?? null;
        $newPrice = $new['price_amount'] ?? null;

        if (
            ! is_string($oldCurrency)
            || ! is_string($newCurrency)
            || $oldCurrency === ''
            || $oldCurrency !== $newCurrency
            || ! is_numeric($oldPrice)
            || ! is_numeric($newPrice)
            || (float) $newPrice >= (float) $oldPrice
        ) {
            return [];
        }

        $this->expireLiveSourceEvent($game->id, 'price_drop', $previous->external_id);

        return [$this->events->upsert([
            'game_id' => $game->id,
            'type' => 'price_drop',
            'title' => "قیمت {$game->name} در {$observation['source_label']} کاهش پیدا کرد",
            'summary' => 'تغییر قیمت از دو مشاهده متوالی همان منبع رسمی تشخیص داده شده.',
            'source_type' => 'external_store',
            'source_name' => $observation['source_label'],
            'source_url' => $observation['source_url'] ?? null,
            'external_id' => $previous->external_id,
            'dedupe_key' => sprintf(
                'source:%s:game:%d:price-drop:%s:%s:%s',
                $previous->source,
                $game->id,
                $newCurrency,
                $this->numberKey((float) $oldPrice),
                $this->numberKey((float) $newPrice),
            ),
            'importance_score' => $this->importance->defaultScore('price_drop'),
            'confidence' => $observation['confidence'] ?? $previous->confidence,
            'old_value' => ['price' => (float) $oldPrice],
            'new_value' => ['price' => (float) $newPrice],
            'metadata' => [
                'source' => $previous->source,
                'currency' => $newCurrency,
                'price_raw_before' => $old['price_raw'] ?? null,
                'price_raw_after' => $new['price_raw'] ?? null,
            ],
            'effective_at' => now(),
            'status' => 'active',
        ])];
    }

    private function expireLiveSourceEvent(int $gameId, string $type, ?string $externalId): void
    {
        if (! $externalId) {
            return;
        }

        GameEvent::query()
            ->where('game_id', $gameId)
            ->where('type', $type)
            ->where('external_id', $externalId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => now()]);
    }

    private function numberKey(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
