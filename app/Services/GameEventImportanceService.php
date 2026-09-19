<?php

namespace App\Services;

use App\Models\GameEvent;

class GameEventImportanceService
{
    public const TYPES = [
        'release_date_changed',
        'released',
        'major_patch',
        'dlc_announced',
        'dlc_released',
        'subscription_added',
        'subscription_leaving',
        'price_drop',
        'free_weekend',
        'major_trailer',
        'preload_available',
        'server_issue',
        'server_restored',
        'major_news',
    ];

    private const META = [
        'release_date_changed' => ['label' => 'تغییر تاریخ انتشار', 'base' => 96, 'signal' => 'news'],
        'released' => ['label' => 'منتشر شد', 'base' => 92, 'signal' => 'news'],
        'major_patch' => ['label' => 'آپدیت مهم', 'base' => 84, 'signal' => 'update'],
        'dlc_announced' => ['label' => 'DLC جدید', 'base' => 72, 'signal' => 'news'],
        'dlc_released' => ['label' => 'DLC منتشر شد', 'base' => 82, 'signal' => 'update'],
        'subscription_added' => ['label' => 'اضافه‌شدن به سرویس', 'base' => 76, 'signal' => 'news'],
        'subscription_leaving' => ['label' => 'خروج از سرویس', 'base' => 91, 'signal' => 'news'],
        'price_drop' => ['label' => 'کاهش قیمت', 'base' => 78, 'signal' => 'news'],
        'free_weekend' => ['label' => 'آخرهفته رایگان', 'base' => 78, 'signal' => 'news'],
        'major_trailer' => ['label' => 'تریلر مهم', 'base' => 68, 'signal' => 'trailer'],
        'preload_available' => ['label' => 'پری‌لود فعال شد', 'base' => 74, 'signal' => 'update'],
        'server_issue' => ['label' => 'اختلال سرور', 'base' => 88, 'signal' => 'update'],
        'server_restored' => ['label' => 'سرورها پایدار شدند', 'base' => 62, 'signal' => 'update'],
        'major_news' => ['label' => 'خبر مهم', 'base' => 64, 'signal' => 'news'],
    ];

    public function label(string $type): string
    {
        return self::META[$type]['label'] ?? 'اتفاق مهم';
    }

    public function defaultScore(string $type): int
    {
        return self::META[$type]['base'] ?? 50;
    }

    public function signalKey(string $type): string
    {
        return self::META[$type]['signal'] ?? 'news';
    }

    public function level(int|float $score): string
    {
        return match (true) {
            $score >= 88 => 'critical',
            $score >= 72 => 'high',
            $score >= 54 => 'medium',
            default => 'normal',
        };
    }

    public function scoreForUser(GameEvent $event, array $profile): array
    {
        $gameScores = $profile['game_scores'] ?? [];
        $signalScores = $profile['signal_scores'] ?? [];
        $followed = array_flip($profile['followed_game_ids'] ?? []);

        $maxGame = max(1, (float) collect($gameScores)->max());
        $maxSignal = max(1, (float) collect($signalScores)->max());
        $gameAffinity = ((float) ($gameScores[$event->game_id] ?? 0) / $maxGame) * 24;
        $signalKey = $this->signalKey($event->type);
        $signalAffinity = ((float) ($signalScores[$signalKey] ?? 0) / $maxSignal) * 7;
        $freshness = $this->freshnessScore($event);
        $confidence = max(0, min(1, (float) $event->confidence)) * 5;
        $score = min(100, round(($event->importance_score * .62) + $gameAffinity + $signalAffinity + $freshness + $confidence, 2));

        $reason = isset($followed[$event->game_id])
            ? 'این اتفاق برای یکی از بازی‌هایی افتاده که دنبال می‌کنی'
            : 'این اتفاق با بازی‌هایی که این روزها بیشتر به علایقت نزدیک‌اند مرتبطه';

        if ($event->importance_score >= 88) {
            $reason = 'خود این اتفاق مهمه و به یکی از بازی‌های موردعلاقه‌ات مربوط می‌شه';
        } elseif ($signalAffinity >= 5) {
            $reason = 'نوع این اتفاق با چیزهایی که معمولاً برات مهم‌ترن هم‌خوانی داره';
        }

        return [
            'score' => $score,
            'level' => $this->level($score),
            'reason' => $reason,
            'type_label' => $this->label($event->type),
            'signal_key' => $signalKey,
        ];
    }

    private function freshnessScore(GameEvent $event): float
    {
        $hours = ($event->detected_at ?? $event->created_at)?->diffInHours(now()) ?? 9999;

        return match (true) {
            $hours <= 12 => 8,
            $hours <= 48 => 6,
            $hours <= 168 => 4,
            $hours <= 720 => 2,
            default => 0,
        };
    }
}
