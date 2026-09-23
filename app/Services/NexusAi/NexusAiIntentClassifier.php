<?php

namespace App\Services\NexusAi;

final class NexusAiIntentClassifier
{
    public function classify(string $question): string
    {
        $q = mb_strtolower(trim($question));

        return match (true) {
            $this->has($q, ['پیشنهاد', 'معرفی کن', 'چی بازی', 'چه بازی', 'چی بزنم', 'دنبال بازی', 'دنبال یه بازی', 'recommend', 'similar to', 'شبیه'])
                || (str_contains($q, 'بازی') && $this->has($q, ['میخوام', 'می‌خوام', 'می خوام'])) => 'recommendation',
            $this->has($q, ['مقایسه', 'بهتره', 'فرق', 'versus', ' vs ', 'compare']) => 'comparison',
            $this->has($q, ['خرید', 'قیمت', 'ارزش', 'بخرم', 'تخفیف', 'price', 'buy', 'worth']) => 'purchase_intent',
            $this->has($q, ['داستان', 'لور', 'پایان', 'شخصیت', 'story', 'lore', 'ending']) => 'story_lore',
            $this->has($q, ['باس', 'build', 'بیلد', 'راهنما', 'چطور', 'مرحله', 'quest', 'walkthrough', 'guide']) => 'game_help',
            $this->has($q, ['fps', 'فریم', 'گرافیک', 'performance', 'رزولوشن', 'resolution', 'vrr']) => 'performance',
            $this->has($q, ['تاریخ انتشار', 'کی میاد', 'release date', 'منتشر', 'عرضه']) => 'release',
            $this->has($q, ['خبر', 'جدیدترین', 'آپدیت', 'news', 'latest', 'update']) => 'news',
            $this->has($q, ['ps5', 'xbox', 'pc', 'پلتفرم', 'کنسول', 'platform']) => 'platform',
            default => 'general',
        };
    }

    private function has(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
