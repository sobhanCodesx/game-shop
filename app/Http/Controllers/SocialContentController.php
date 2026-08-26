<?php

namespace App\Http\Controllers;

use App\Models\SocialContent;
use Illuminate\Support\Facades\Storage;
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
                'thumbnail_url' => $content->thumbnail ? Storage::url($content->thumbnail) : null,
                'published_at' => $content->published_at?->toISOString(),
            ],
        ]);
    }
}
