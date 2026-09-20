<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialContent;
use App\Models\VideoWatchProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWatchProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $progress = $request->user()
            ->videoWatchProgress()
            ->whereHas('content', fn ($query) => $query
                ->published()
                ->whereIn('type', ['video', 'short']))
            ->orderByDesc('last_watched_at')
            ->get([
                'social_content_id',
                'position_seconds',
                'duration_seconds',
                'completed',
                'updated_at',
            ])
            ->mapWithKeys(fn (VideoWatchProgress $item) => [
                (string) $item->social_content_id => [
                    'content_id' => (int) $item->social_content_id,
                    'position' => (int) $item->position_seconds,
                    'duration' => (int) ($item->duration_seconds ?? 0),
                    'completed' => (bool) $item->completed,
                    'updated_at' => $item->updated_at?->toISOString(),
                ],
            ]);

        return response()->json(['progress' => $progress]);
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
