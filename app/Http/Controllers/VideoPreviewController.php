<?php

namespace App\Http\Controllers;

use App\Models\SocialContent;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;

class VideoPreviewController extends Controller
{
    public function __invoke(SocialContent $content): JsonResponse
    {
        abort_unless(
            $content->type === 'video'
            && $content->status === 'published'
            && $content->published_at?->isPast(),
            404,
        );

        $content->loadMissing('media');
        $videoMedia = $content->media->first(
            fn ($media) => $media->type === 'video' && filled($media->path),
        );
        $videoPath = $content->video_path ?: $videoMedia?->path;
        abort_unless($videoPath, 404);

        return response()->json([
            'id' => $content->id,
            'video_url' => MediaStorage::url($videoPath),
            'duration' => $content->duration ?: $videoMedia?->duration,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
