<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShortRequest;
use App\Models\SocialContent;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShortController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Shorts/Index', [
            'shorts' => SocialContent::query()->where('type', 'short')->orderBy('sort_order')->orderByDesc('id')->get()->map(fn (SocialContent $short) => [
                ...$short->only(['id', 'title', 'media_type', 'duration', 'status', 'sort_order']),
                'preview_url' => MediaStorage::url($short->video_path),
                'thumbnail_url' => MediaStorage::url($short->thumbnail),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Shorts/Form', ['short' => null]);
    }

    public function store(ShortRequest $request, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        $short = new SocialContent($request->safe()->only(['title', 'excerpt', 'link_url', 'link_label', 'status', 'sort_order']));
        $short->fill(['user_id' => $request->user()->id, 'type' => 'short', 'slug' => $this->uniqueSlug($request->string('title')->toString())]);
        $short->published_at = $short->status === 'published' ? now() : null;
        $this->claimAndStore($request, $short, $optimizer, $uploads);
        $short->save();

        return to_route('admin.shorts.index')->with('success', 'شورت با موفقیت ثبت شد.');
    }

    public function edit(SocialContent $short): Response
    {
        abort_unless($short->type === 'short', 404);

        return Inertia::render('Admin/Shorts/Form', ['short' => [
            ...$short->only(['id', 'title', 'excerpt', 'link_url', 'link_label', 'media_type', 'status', 'sort_order']),
            'preview_url' => MediaStorage::url($short->video_path),
            'thumbnail_url' => MediaStorage::url($short->thumbnail),
        ]]);
    }

    public function update(ShortRequest $request, SocialContent $short, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        abort_unless($short->type === 'short', 404);
        $short->fill($request->safe()->only(['title', 'excerpt', 'link_url', 'link_label', 'status', 'sort_order']));
        $short->published_at = $short->status === 'published' ? ($short->published_at ?? now()) : null;
        if ($request->hasFile('media') || $request->filled('upload_token')) {
            $old = array_filter([$short->video_path, $short->thumbnail]);
            $this->claimAndStore($request, $short, $optimizer, $uploads);
            MediaStorage::disk()->delete($old);
        } elseif ($request->hasFile('thumbnail')) {
            $oldThumbnail = $short->thumbnail;
            $short->thumbnail = $optimizer->store($request->file('thumbnail'), 'shorts/thumbnails')['path'];
            if ($oldThumbnail && $oldThumbnail !== $short->video_path) {
                MediaStorage::disk()->delete($oldThumbnail);
            }
        }
        $short->save();

        return to_route('admin.shorts.index')->with('success', 'شورت ویرایش شد.');
    }

    public function destroy(SocialContent $short): RedirectResponse
    {
        abort_unless($short->type === 'short', 404);
        MediaStorage::disk()->delete(array_filter([$short->video_path, $short->thumbnail]));
        $short->delete();

        return back()->with('success', 'شورت حذف شد.');
    }

    private function claimAndStore(ShortRequest $request, SocialContent $short, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): void
    {
        $token = $request->string('upload_token')->toString();
        $file = $token ? $uploads->claim($request->user()->id, $token) : $request->file('media');
        try {
            $this->storeMedia($short, $file, $optimizer, $request->file('thumbnail'));
        } finally {
            if ($token) {
                $uploads->forget($request->user()->id, $token);
            }
        }
    }

    private function storeMedia(SocialContent $short, UploadedFile $file, MediaOptimizationService $optimizer, ?UploadedFile $customThumbnail = null): void
    {
        $stored = $optimizer->store($file, 'shorts');
        $short->media_type = $stored['type'];
        $short->video_path = $stored['path'];
        $short->video_mime = $file->getMimeType();
        if ($stored['type'] === 'video') {
            $metadata = $optimizer->videoMetadata($stored['path'], 'shorts/thumbnails');
            $short->thumbnail = $customThumbnail
                ? $optimizer->store($customThumbnail, 'shorts/thumbnails')['path']
                : $metadata['thumbnail'];
            $short->duration = $metadata['duration'];
        } else {
            $short->thumbnail = $stored['path'];
            $short->duration = 5;
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'short';
        $slug = $base;
        $i = 2;
        while (SocialContent::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
