<?php

namespace App\Http\Controllers;

use App\Models\SocialContent;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaStorage;
use Inertia\Inertia;
use Inertia\Response;

class SocialContentController extends Controller
{
    public function show(string $type, SocialContent $content): Response
    {
        $expectedType = match ($type) {
            'posts' => 'post', 'videos' => 'video', 'shorts' => 'short', default => abort(404),
        };

        abort_unless($content->type === $expectedType && $content->status === 'published' && $content->published_at?->isPast(), 404);

        return Inertia::render('Content/Show', [
            'content' => [
                ...$content->only(['title', 'slug', 'type', 'excerpt', 'duration', 'views']),
                'thumbnail_url' => MediaStorage::url($content->thumbnail),
                'video_url' => MediaStorage::url($content->video_path),
                'published_at' => $content->published_at?->toISOString(),
            ],
        ]);
    }
}
