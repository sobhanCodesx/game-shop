<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoRequest;
use App\Models\Game;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function index(): Response
    {
        $videos = SocialContent::query()->where('type', 'video')->with('game:id,name')->latest('id')->paginate(12)->withQueryString();

        return Inertia::render('Admin/Videos/Index', [
            'videos' => [
                'data' => collect($videos->items())->map(fn (SocialContent $video) => [
                    ...$video->only(['id', 'title', 'excerpt', 'duration', 'views', 'status', 'featured']),
                    'channel' => $video->game?->name,
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
        return Inertia::render('Admin/Videos/Form', [
            'video' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(VideoRequest $request, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        $video = new SocialContent([
            ...$request->safe()->only(['title', 'excerpt', 'body', 'seo_title', 'seo_description', 'status', 'featured', 'game_id', 'allow_comments']),
            'user_id' => $request->user()->id,
            'type' => 'video',
            'slug' => $this->uniqueSlug($request->string('title')->toString()),
            'published_at' => $request->string('status')->toString() === 'published' ? now() : null,
        ]);
        $this->prepareEditorialContent($video);
        $token = $request->string('upload_token')->toString();
        $file = $token ? $uploads->claim($request->user()->id, $token) : $request->file('video');
        try {
            $this->storeFile($video, $file, $optimizer, $request->file('thumbnail'), $request->integer('client_duration') ?: null);
            $video->save();
            $video->playlists()->sync($this->playlistSync($request->validated('playlist_ids', [])));
        } catch (\Throwable $exception) {
            if ($video->video_path || $video->thumbnail) {
                $this->deleteFiles($video);
            }

            throw $exception;
        } finally {
            if ($token && $video->exists) {
                $uploads->forget($request->user()->id, $token);
            }
        }

        return to_route('admin.videos.index')->with('success', 'ویدیو با موفقیت آپلود شد.');
    }

    public function edit(SocialContent $video): Response
    {
        abort_unless($video->type === 'video', 404);

        return Inertia::render('Admin/Videos/Form', [
            'video' => [
                ...$video->only(['id', 'title', 'excerpt', 'body', 'seo_title', 'seo_description', 'status', 'featured', 'duration', 'game_id', 'allow_comments']),
                'video_url' => MediaStorage::url($video->video_path),
                'thumbnail_url' => MediaStorage::url($video->thumbnail),
                'playlist_ids' => $video->playlists()->pluck('video_playlists.id'),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(VideoRequest $request, SocialContent $video, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        abort_unless($video->type === 'video', 404);
        $video->fill($request->safe()->only(['title', 'excerpt', 'body', 'seo_title', 'seo_description', 'status', 'featured', 'game_id', 'allow_comments']));
        $this->prepareEditorialContent($video);
        $video->published_at = $video->status === 'published' ? ($video->published_at ?? now()) : null;
        $token = $request->string('upload_token')->toString();
        $oldThumbnail = null;
        if ($request->hasFile('video') || $token) {
            $oldFiles = array_filter([$video->video_path, $video->thumbnail]);
            $file = $token ? $uploads->claim($request->user()->id, $token) : $request->file('video');
            try {
                $this->storeFile($video, $file, $optimizer, $request->file('thumbnail'), $request->integer('client_duration') ?: null);
            } finally {
                if ($token) {
                    $uploads->forget($request->user()->id, $token);
                }
            }
            MediaStorage::disk()->delete($oldFiles);
        } elseif ($request->hasFile('thumbnail')) {
            $oldThumbnail = $video->thumbnail;
            $video->thumbnail = $optimizer->store($request->file('thumbnail'), 'videos/thumbnails')['path'];
        }
        $video->save();
        if ($oldThumbnail) {
            MediaStorage::disk()->delete($oldThumbnail);
        }
        $video->playlists()->sync($this->playlistSync($request->validated('playlist_ids', [])));

        return to_route('admin.videos.index')->with('success', 'ویدیو با موفقیت ویرایش شد.');
    }

    public function destroy(SocialContent $video): RedirectResponse
    {
        abort_unless($video->type === 'video', 404);
        $this->deleteFiles($video);
        $video->delete();

        return back()->with('success', 'ویدیو حذف شد.');
    }

    private function storeFile(
        SocialContent $video,
        UploadedFile $file,
        MediaOptimizationService $optimizer,
        ?UploadedFile $browserThumbnail = null,
        ?int $browserDuration = null,
    ): void {
        $stored = $optimizer->store($file, 'videos');
        $video->video_path = $stored['path'];
        $video->video_mime = $file->getMimeType() ?: $file->getClientMimeType() ?: 'video/mp4';
        $video->thumbnail = $browserThumbnail
            ? $optimizer->store($browserThumbnail, 'videos/thumbnails')['path']
            : null;
        $video->duration = $browserDuration;
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

    private function prepareEditorialContent(SocialContent $video): void
    {
        $video->excerpt = RichText::plainText($video->excerpt);
        $video->body = RichText::sanitize($video->body);
        $video->seo_title = filled($video->seo_title) ? trim($video->seo_title) : null;
        $video->seo_description = filled($video->seo_description) ? trim($video->seo_description) : null;
    }

    private function formOptions(): array
    {
        return [
            'games' => Game::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name']),
            'playlists' => VideoPlaylist::query()->orderBy('sort_order')->orderBy('title')->get(['id', 'game_id', 'title']),
        ];
    }

    private function playlistSync(array $ids): array
    {
        return collect($ids)->values()->mapWithKeys(fn ($id, $position) => [(int) $id => ['position' => $position]])->all();
    }
}
