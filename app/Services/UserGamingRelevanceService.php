<?php

namespace App\Services;

use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserGamingRelevanceService
{
    private const SIGNAL_LABELS = [
        'update' => 'آپدیت‌ها',
        'trailer' => 'تریلرها',
        'news' => 'خبرهای مهم',
        'review' => 'نقد و بررسی',
        'video' => 'ویدیوها',
        'story' => 'محتوای داستانی',
    ];

    public function profile(Request $request, Collection $followedGames): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->emptyProfile();
        }

        $gameScores = [];
        $gameSources = [];
        $signalScores = [];
        $interactionCount = min($followedGames->count(), 6) * 2;

        foreach ($followedGames as $game) {
            $this->addGameScore($gameScores, $gameSources, (int) $game->id, 60, 'follow');
        }

        $saved = DB::table('social_content_saves as saves')
            ->join('social_contents as content', 'content.id', '=', 'saves.social_content_id')
            ->where('saves.user_id', $user->id)
            ->whereNotNull('content.game_id')
            ->where('saves.created_at', '>=', now()->subDays(180))
            ->latest('saves.created_at')
            ->limit(100)
            ->get([
                'content.game_id',
                'content.type',
                'content.feed_type',
                'content.feed_badge',
                'saves.created_at as signal_at',
            ]);

        foreach ($saved as $row) {
            $weight = 28 * $this->recencyMultiplier($row->signal_at);
            $this->addGameScore($gameScores, $gameSources, (int) $row->game_id, $weight, 'save');
            $this->addSignalScore($signalScores, $this->signalKey($row), 18 * $this->recencyMultiplier($row->signal_at));
            $interactionCount += 2;
        }

        $reactions = DB::table('social_content_reactions as reactions')
            ->join('social_contents as content', 'content.id', '=', 'reactions.social_content_id')
            ->where('reactions.user_id', $user->id)
            ->whereNotNull('content.game_id')
            ->where('reactions.created_at', '>=', now()->subDays(120))
            ->latest('reactions.created_at')
            ->limit(120)
            ->get([
                'content.game_id',
                'content.type',
                'content.feed_type',
                'content.feed_badge',
                'reactions.type as reaction_type',
                'reactions.created_at as signal_at',
            ]);

        foreach ($reactions as $row) {
            $base = $row->reaction_type === 'like' ? 14 : 10;
            $weight = $base * $this->recencyMultiplier($row->signal_at);
            $this->addGameScore($gameScores, $gameSources, (int) $row->game_id, $weight, 'reaction');
            $this->addSignalScore($signalScores, $this->signalKey($row), 10 * $this->recencyMultiplier($row->signal_at));
            $interactionCount++;
        }

        $watchProgress = DB::table('video_watch_progress as progress')
            ->join('social_contents as content', 'content.id', '=', 'progress.social_content_id')
            ->where('progress.user_id', $user->id)
            ->whereNotNull('content.game_id')
            ->where('progress.last_watched_at', '>=', now()->subDays(120))
            ->latest('progress.last_watched_at')
            ->limit(100)
            ->get([
                'content.game_id',
                'content.type',
                'content.feed_type',
                'content.feed_badge',
                'progress.position_seconds',
                'progress.duration_seconds',
                'progress.completed',
                'progress.last_watched_at as signal_at',
            ]);

        foreach ($watchProgress as $row) {
            $duration = max(1, (int) ($row->duration_seconds ?? 0));
            $ratio = min(1, max(0, (int) $row->position_seconds / $duration));
            $base = $row->completed ? 24 : 8 + (16 * $ratio);
            $weight = $base * $this->recencyMultiplier($row->signal_at);
            $this->addGameScore($gameScores, $gameSources, (int) $row->game_id, $weight, 'watch');
            $this->addSignalScore($signalScores, $this->signalKey($row), 12 * max(.45, $ratio));
            $interactionCount += $row->completed ? 2 : 1;
        }

        $sessionIds = $request->hasSession()
            ? collect([
                ...(array) $request->session()->get('viewed_posts', []),
                ...(array) $request->session()->get('viewed_videos', []),
                ...(array) $request->session()->get('viewed_shorts', []),
            ])->map(fn ($id) => (int) $id)->filter()->unique()->values()
            : collect();

        if ($sessionIds->isNotEmpty()) {
            $viewed = SocialContent::query()
                ->whereIn('id', $sessionIds->all())
                ->whereNotNull('game_id')
                ->get(['id', 'game_id', 'type', 'feed_type', 'feed_badge'])
                ->keyBy('id');

            $sessionIds->reverse()->values()->take(50)->each(function (int $id, int $index) use (
                $viewed,
                &$gameScores,
                &$gameSources,
                &$signalScores,
                &$interactionCount,
            ) {
                $content = $viewed->get($id);

                if (! $content) {
                    return;
                }

                $weight = max(2.5, 8 - ($index * .18));
                $this->addGameScore($gameScores, $gameSources, (int) $content->game_id, $weight, 'view');
                $this->addSignalScore($signalScores, $this->signalKey($content), max(1.5, 5 - ($index * .1)));
                $interactionCount++;
            });
        }

        arsort($gameScores);
        arsort($signalScores);

        $focusGameId = array_key_first($gameScores);
        $focusReason = $focusGameId
            ? $this->focusReason($gameSources[$focusGameId] ?? [])
            : null;

        $confidence = match (true) {
            $interactionCount >= 24 => ['key' => 'strong', 'label' => 'شناخت قوی از سلیقه‌ات'],
            $interactionCount >= 8 => ['key' => 'growing', 'label' => 'داره بهتر می‌شناستت'],
            default => ['key' => 'learning', 'label' => 'در حال شناخت سلیقه‌ات'],
        };

        $topSignals = collect($signalScores)
            ->take(3)
            ->map(fn (float $score, string $key) => [
                'key' => $key,
                'label' => self::SIGNAL_LABELS[$key] ?? 'محتوای گیمینگ',
            ])
            ->values()
            ->all();

        return [
            'game_scores' => $gameScores,
            'signal_scores' => $signalScores,
            'followed_game_ids' => $followedGames->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'focus_game_id' => $focusGameId ? (int) $focusGameId : null,
            'focus_reason' => $focusReason,
            'confidence' => $confidence,
            'top_signals' => $topSignals,
            'interaction_count' => $interactionCount,
        ];
    }

    public function rankContents(Collection $contents, array $profile): Collection
    {
        $gameScores = $profile['game_scores'] ?? [];
        $signalScores = $profile['signal_scores'] ?? [];
        $followed = array_flip($profile['followed_game_ids'] ?? []);
        $maxGameScore = max(1, (float) collect($gameScores)->max());
        $maxSignalScore = max(1, (float) collect($signalScores)->max());

        return $contents
            ->map(function (SocialContent $content) use (
                $gameScores,
                $signalScores,
                $followed,
                $maxGameScore,
                $maxSignalScore,
            ) {
                $gameId = (int) ($content->game_id ?? 0);
                $signalKey = $this->signalKey($content);
                $gameAffinity = ((float) ($gameScores[$gameId] ?? 0) / $maxGameScore) * 52;
                $signalAffinity = ((float) ($signalScores[$signalKey] ?? 0) / $maxSignalScore) * 18;
                $importance = $this->importanceScore($content);
                $recency = $this->contentRecencyScore($content);
                $score = round($gameAffinity + $signalAffinity + $importance + $recency, 2);

                $reason = isset($followed[$gameId])
                    ? 'مرتبط با یکی از بازی‌هایی که دنبال می‌کنی'
                    : 'بر اساس چیزهایی که بیشتر برات مهم شده';

                if ($signalAffinity >= 12 && isset(self::SIGNAL_LABELS[$signalKey])) {
                    $reason = 'چون '.self::SIGNAL_LABELS[$signalKey].' بیشتر با علایقت جور درمیاد';
                }

                return [
                    'content' => $content,
                    'score' => $score,
                    'priority' => match (true) {
                        $score >= 86 => 'critical',
                        $score >= 68 => 'high',
                        $score >= 48 => 'medium',
                        default => 'normal',
                    },
                    'reason' => $reason,
                    'signal_key' => $signalKey,
                    'signal_label' => self::SIGNAL_LABELS[$signalKey] ?? 'محتوای مرتبط',
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    private function emptyProfile(): array
    {
        return [
            'game_scores' => [],
            'signal_scores' => [],
            'followed_game_ids' => [],
            'focus_game_id' => null,
            'focus_reason' => null,
            'confidence' => ['key' => 'learning', 'label' => 'در حال شناخت سلیقه‌ات'],
            'top_signals' => [],
            'interaction_count' => 0,
        ];
    }

    private function addGameScore(array &$scores, array &$sources, int $gameId, float $points, string $source): void
    {
        if ($gameId <= 0 || $points <= 0) {
            return;
        }

        $scores[$gameId] = ($scores[$gameId] ?? 0) + $points;
        $sources[$gameId][$source] = ($sources[$gameId][$source] ?? 0) + $points;
    }

    private function addSignalScore(array &$scores, string $key, float $points): void
    {
        $scores[$key] = ($scores[$key] ?? 0) + $points;
    }

    private function recencyMultiplier($value): float
    {
        $date = Carbon::parse($value);
        $days = $date->diffInDays(now());

        return match (true) {
            $days <= 7 => 1,
            $days <= 30 => .78,
            $days <= 90 => .55,
            default => .35,
        };
    }

    private function signalKey(object $content): string
    {
        $badge = (string) ($content->feed_badge ?? '');
        $feedType = (string) ($content->feed_type ?? '');
        $type = (string) ($content->type ?? '');

        if (in_array($badge, ['update', 'patch'], true) || $feedType === 'game_update') {
            return 'update';
        }

        if ($badge === 'trailer' || $feedType === 'trailer') {
            return 'trailer';
        }

        if (in_array($badge, ['breaking', 'news'], true) || in_array($feedType, ['news', 'article'], true)) {
            return 'news';
        }

        if ($badge === 'review' || $feedType === 'review') {
            return 'review';
        }

        if ($type === 'video' || in_array($feedType, ['video', 'clip'], true)) {
            return 'video';
        }

        return 'story';
    }

    private function focusReason(array $sources): string
    {
        arsort($sources);
        $primary = array_key_first($sources);

        return match ($primary) {
            'save' => 'این بازی با چیزهایی که برای بعد نگه می‌داری هم‌خوانی بیشتری دارد',
            'watch' => 'این بازی با چیزهایی که بیشتر تماشا می‌کنی هم‌خوانی دارد',
            'reaction' => 'این بازی با تعاملات اخیرت هم‌خوانی بیشتری دارد',
            'view' => 'این بازی این روزها به علایقت نزدیک‌تر شده',
            default => 'این بازی جزو دنبال‌شده‌های اصلی توست',
        };
    }

    private function importanceScore(SocialContent $content): float
    {
        $signalScore = match ($this->signalKey($content)) {
            'update' => 20,
            'trailer' => 17,
            'news' => 15,
            'review' => 11,
            'video' => 8,
            default => 6,
        };
        $breakingBoost = $content->feed_badge === 'breaking' ? 8 : 0;

        return $signalScore + $breakingBoost + ($content->featured ? 6 : 0);
    }

    private function contentRecencyScore(SocialContent $content): float
    {
        if (! $content->published_at) {
            return 0;
        }

        $hours = $content->published_at->diffInHours(now());

        return match (true) {
            $hours <= 24 => 20,
            $hours <= 72 => 15,
            $hours <= 168 => 10,
            $hours <= 720 => 5,
            default => 1,
        };
    }
}
