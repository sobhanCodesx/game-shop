<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoPlaylistRequest;
use App\Models\Game;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoPlaylistController extends Controller
{
    public function index(): Response
    {
        $playlists = VideoPlaylist::query()->with('game:id,name,cover')->withCount('videos')
            ->orderBy('sort_order')->latest('id')->paginate(12)->withQueryString();

        return Inertia::render('Admin/Playlists/Index', [
            'playlists' => [
                'data' => collect($playlists->items())->map(fn (VideoPlaylist $playlist) => [
                    ...$playlist->only(['id', 'game_id', 'title', 'description', 'visibility', 'sort_order']),
                    'game' => $playlist->game?->name,
                    'logo_url' => MediaStorage::url($playlist->logo),
                    'channel_image_url' => MediaStorage::url($playlist->game?->cover),
                    'videos_count' => $playlist->videos_count,
                ]),
                'current_page' => $playlists->currentPage(),
                'last_page' => $playlists->lastPage(),
                'from' => $playlists->firstItem(),
                'to' => $playlists->lastItem(),
                'total' => $playlists->total(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Playlists/Form', [
            'playlist' => null,
            'games' => $this->games(),
            'studios' => $this->studios(),
        ]);
    }

    public function store(VideoPlaylistRequest $request, MediaOptimizationService $optimizer): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo']);
        $data['studio_id'] ??= Game::query()->whereKey($data['game_id'])->value('studio_id');
        $data['description'] = RichText::sanitize($data['description'] ?? null);
        $data['slug'] = $this->uniqueSlug((int) $data['game_id'], $data['title']);
        if ($request->hasFile('logo')) {
            $data['logo'] = $optimizer->store($request->file('logo'), 'video-playlists/logos')['path'];
        }
        VideoPlaylist::query()->create($data);

        return to_route('admin.video-playlists.index')->with('success', 'کالکشن ویدیو ساخته شد.');
    }

    public function edit(VideoPlaylist $playlist): Response
    {
        return Inertia::render('Admin/Playlists/Form', [
            'playlist' => [
                ...$playlist->only(['id', 'game_id', 'studio_id', 'title', 'description', 'visibility', 'sort_order']),
                'logo_url' => MediaStorage::url($playlist->logo),
            ],
            'games' => $this->games(),
            'studios' => $this->studios(),
        ]);
    }

    public function update(VideoPlaylistRequest $request, VideoPlaylist $playlist, MediaOptimizationService $optimizer): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo']);
        $data['studio_id'] ??= Game::query()->whereKey($data['game_id'])->value('studio_id');
        $data['description'] = RichText::sanitize($data['description'] ?? null);
        if ($playlist->title !== $data['title'] || $playlist->game_id !== (int) $data['game_id']) {
            $data['slug'] = $this->uniqueSlug((int) $data['game_id'], $data['title'], $playlist->id);
        }
        $oldLogo = null;
        if ($request->hasFile('logo')) {
            $oldLogo = $playlist->logo;
            $data['logo'] = $optimizer->store($request->file('logo'), 'video-playlists/logos')['path'];
        } elseif ($request->boolean('remove_logo')) {
            $oldLogo = $playlist->logo;
            $data['logo'] = null;
        }
        $playlist->update($data);
        if ($oldLogo) {
            MediaStorage::disk()->delete($oldLogo);
        }

        return to_route('admin.video-playlists.index')->with('success', 'کالکشن به‌روزرسانی شد.');
    }

    public function destroy(VideoPlaylist $playlist): RedirectResponse
    {
        if ($playlist->logo) {
            MediaStorage::disk()->delete($playlist->logo);
        }
        $playlist->delete();

        return back()->with('success', 'کالکشن حذف شد.');
    }

    private function games(): array
    {
        return Game::query()->whereIn('status', ['active', 'published'])
            ->orderBy('name')->get(['id', 'name', 'studio_id'])->toArray();
    }

    private function studios(): array
    {
        return Studio::query()->where('status', 'active')
            ->orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function uniqueSlug(int $gameId, string $title, ?int $ignore = null): string
    {
        $base = Str::slug($title) ?: 'playlist';
        $slug = $base;
        $counter = 2;
        while (VideoPlaylist::query()->where('game_id', $gameId)->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
