<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoRequest;
use App\Models\SocialContent;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function index(): Response
    {
        $videos = SocialContent::query()->where('type', 'video')->latest('id')->paginate(12)->withQueryString();

        return Inertia::render('Admin/Videos/Index', [
            'videos' => [
                'data' => collect($videos->items())->map(fn (SocialContent $video) => [
                    ...$video->only(['id', 'title', 'excerpt', 'duration', 'views', 'status', 'featured']),
                    'thumbnail_url' => MediaStorage::url($video->thumbnail),
                    'edit_url' => route('admin.videos.edit', $video),
                ]),
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'total' => $videos->total(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Videos/Form', ['video' => null]);
    }

    public function store(VideoRequest $request, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        $video = new SocialContent([
            ...$request->safe()->only(['title', 'excerpt', 'status', 'featured']),
            'user_id' => $request->user()->id,
            'type' => 'video',
            'slug' => $this->uniqueSlug($request->string('title')->toString()),
            'published_at' => $request->string('status')->toString() === 'published' ? now() : null,
        ]);
        $video->excerpt = $this->sanitizeDescription($video->excerpt);
        $token = $request->string('upload_token')->toString();
        $file = $token ? $uploads->claim($request->user()->id, $token) : $request->file('video');
        try {
            $this->storeFile($video, $file, $optimizer);
        } finally {
            if ($token) {
                $uploads->forget($request->user()->id, $token);
            }
        }
        $video->save();

        return to_route('admin.videos.index')->with('success', 'ویدیو با موفقیت آپلود شد.');
    }

    public function edit(SocialContent $video): Response
    {
        abort_unless($video->type === 'video', 404);

        return Inertia::render('Admin/Videos/Form', [
            'video' => [
                ...$video->only(['id', 'title', 'excerpt', 'status', 'featured', 'duration']),
                'video_url' => MediaStorage::url($video->video_path),
            ],
        ]);
    }

    public function update(VideoRequest $request, SocialContent $video, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        abort_unless($video->type === 'video', 404);
        $video->fill($request->safe()->only(['title', 'excerpt', 'status', 'featured']));
        $video->excerpt = $this->sanitizeDescription($video->excerpt);
        $video->published_at = $video->status === 'published' ? ($video->published_at ?? now()) : null;
        $token = $request->string('upload_token')->toString();
        if ($request->hasFile('video') || $token) {
            $oldFiles = array_filter([$video->video_path, $video->thumbnail]);
            $file = $token ? $uploads->claim($request->user()->id, $token) : $request->file('video');
            try {
                $this->storeFile($video, $file, $optimizer);
            } finally {
                if ($token) {
                    $uploads->forget($request->user()->id, $token);
                }
            }
            MediaStorage::disk()->delete($oldFiles);
        }
        $video->save();

        return to_route('admin.videos.index')->with('success', 'ویدیو با موفقیت ویرایش شد.');
    }

    public function destroy(SocialContent $video): RedirectResponse
    {
        abort_unless($video->type === 'video', 404);
        $this->deleteFiles($video);
        $video->delete();

        return back()->with('success', 'ویدیو حذف شد.');
    }

    private function storeFile(SocialContent $video, UploadedFile $file, MediaOptimizationService $optimizer): void
    {
        $stored = $optimizer->store($file, 'videos');
        $metadata = $optimizer->videoMetadata($stored['path'], 'videos/thumbnails');
        $video->video_path = $stored['path'];
        $video->video_mime = 'video/mp4';
        $video->thumbnail = $metadata['thumbnail'];
        $video->duration = $metadata['duration'];
    }

    private function deleteFiles(SocialContent $video): void
    {
        MediaStorage::disk()->delete(array_filter([$video->video_path, $video->thumbnail]));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'video';
        $slug = $base;
        $counter = 2;
        while (SocialContent::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function sanitizeDescription(?string $description): ?string
    {
        if (blank($description)) {
            return null;
        }

        return strip_tags($description, '<p><br><strong><b><em><i><u><ul><ol><li>');
    }
}
