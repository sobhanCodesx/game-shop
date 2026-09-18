<?php

namespace App\Http\Controllers;

use App\Models\SocialContent;
use App\Models\VideoWatchProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoProgressController extends Controller
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
                    'contentId' => (int) $item->social_content_id,
                    'position' => (int) $item->position_seconds,
                    'duration' => (int) ($item->duration_seconds ?? 0),
                    'completed' => (bool) $item->completed,
                    'updatedAt' => $item->updated_at?->getTimestampMs() ?? 0,
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
                'contentId' => $content->id,
                'position' => (int) $progress->position_seconds,
                'duration' => (int) ($progress->duration_seconds ?? 0),
                'completed' => (bool) $progress->completed,
                'updatedAt' => $progress->updated_at?->getTimestampMs() ?? now()->getTimestampMs(),
            ],
        ]);
    }
}
