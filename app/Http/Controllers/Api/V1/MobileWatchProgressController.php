<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialContent;
use App\Models\VideoWatchProgress;
use App\Services\StorefrontDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWatchProgressController extends Controller
{
    public function index(Request $request, StorefrontDataService $storefront): JsonResponse
    {
        $progress = $request->user()
            ->videoWatchProgress()
            ->whereHas('content', fn ($query) => $query
                ->published()
                ->whereIn('type', ['video', 'short']))
            ->with([
                'content.game:id,name,slug,cover',
                'content.game.playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
                'content.media',
            ])
            ->orderByDesc('last_watched_at')
            ->get([
                'id',
                'user_id',
                'social_content_id',
                'position_seconds',
                'duration_seconds',
                'completed',
                'last_watched_at',
                'updated_at',
            ])
            ->mapWithKeys(function (VideoWatchProgress $item) use ($storefront): array {
                $content = $item->content;

                return [
                    (string) $item->social_content_id => [
                        'content_id' => (int) $item->social_content_id,
                        'position' => (int) $item->position_seconds,
                        'duration' => (int) ($item->duration_seconds ?? 0),
                        'completed' => (bool) $item->completed,
                        'updated_at' => $item->last_watched_at?->toISOString()
                            ?? $item->updated_at?->toISOString(),
                        'content' => $content ? $storefront->content($content) : null,
                    ],
                ];
            });

        return response()->json([
            'progress' => $progress,
            'items' => $progress->values(),
        ]);
    }

    public function store(Request $request, SocialContent $content): JsonResponse
    {
        abort_unless(
            in_array($content->type, ['video', 'short'], true)
                && $content->status === 'published'
                && $content->published_at?->isPast(),
            404,
        );

        $validated = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0', 'max:43200'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        $duration = (int) ($validated['duration_seconds'] ?? $content->duration ?? 0);
        $position = (int) $validated['position_seconds'];

        if ($duration > 0) {
            $position = min($position, $duration);
        }

        $completed = $duration > 0
            && ($position >= $duration - 5 || $position / $duration >= 0.98);

        $progress = VideoWatchProgress::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'social_content_id' => $content->id,
            ],
            [
                'position_seconds' => $position,
                'duration_seconds' => $duration > 0 ? $duration : null,
                'completed' => $completed,
                'last_watched_at' => now(),
            ],
        );

        return response()->json([
            'progress' => [
                'content_id' => $content->id,
                'position' => (int) $progress->position_seconds,
                'duration' => (int) ($progress->duration_seconds ?? 0),
                'completed' => (bool) $progress->completed,
                'updated_at' => $progress->updated_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(Request $request, SocialContent $content): JsonResponse
    {
        $deleted = $request->user()
            ->videoWatchProgress()
            ->where('social_content_id', $content->id)
            ->delete();

        return response()->json(['deleted' => $deleted > 0]);
    }
}
