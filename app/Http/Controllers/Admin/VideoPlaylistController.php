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
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoPlaylistController extends Controller
{
    public function index(Request $request): Response
    {
        $games = Game::query()->withCount('playlists')->latest('id')
            ->paginate(12, ['id', 'name', 'slug', 'cover', 'background', 'status'], 'games_page')
            ->withQueryString();
        $selectedGame = $request->integer('game')
            ? Game::query()->findOrFail($request->integer('game'))
            : null;
        $playlists = $selectedGame
            ? VideoPlaylist::query()->whereBelongsTo($selectedGame)->withCount('videos')
                ->orderBy('sort_order')->latest('id')
                ->paginate(12, ['*'], 'playlists_page')->withQueryString()
            : null;

        return Inertia::render('Admin/Playlists/Index', [
            'games' => [
                'data' => collect($games->items())->map(fn (Game $game) => [
                    ...$game->only(['id', 'name', 'slug', 'status']),
                    'image_url' => MediaStorage::url($game->background ?: $game->cover),
                    'cover_url' => MediaStorage::url($game->cover),
                    'playlists_count' => $game->playlists_count,
                ]),
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
                'from' => $games->firstItem(),
                'to' => $games->lastItem(),
                'total' => $games->total(),
            ],
            'selectedGame' => $selectedGame ? [
                ...$selectedGame->only(['id', 'name', 'slug']),
                'cover_url' => MediaStorage::url($selectedGame->cover),
            ] : null,
            'playlists' => $playlists ? [
                'data' => collect($playlists->items())->map(fn (VideoPlaylist $playlist) => [
                    ...$playlist->only(['id', 'game_id', 'title', 'description', 'visibility', 'sort_order']),
                    'logo_url' => MediaStorage::url($playlist->logo),
                    'videos_count' => $playlist->videos_count,
                ]),
                'current_page' => $playlists->currentPage(),
                'last_page' => $playlists->lastPage(),
                'from' => $playlists->firstItem(),
                'to' => $playlists->lastItem(),
                'total' => $playlists->total(),
            ] : null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Playlists/Form', [
            'playlist' => null,
            'studios' => $this->studios(),
            'games' => $this->games(),
        ]);
    }

    public function store(VideoPlaylistRequest $request, MediaOptimizationService $optimizer): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo']);
        $data['description'] = RichText::sanitize($data['description'] ?? null);
        $data['slug'] = $this->uniqueSlug($data['title']);
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
            'studios' => $this->studios(),
            'games' => $this->games(),
        ]);
    }

    public function update(VideoPlaylistRequest $request, VideoPlaylist $playlist, MediaOptimizationService $optimizer): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo']);
        $data['description'] = RichText::sanitize($data['description'] ?? null);
        if ($playlist->title !== $data['title']) {
            $data['slug'] = $this->uniqueSlug($data['title'], $playlist->id);
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

    private function studios(): array
    {
        return Studio::query()->where('status', 'active')
            ->orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function games(): array
    {
        return Game::query()->whereIn('status', ['active', 'published'])
            ->latest()->get(['id', 'name'])->toArray();
    }

    private function uniqueSlug(string $title, ?int $ignore = null): string
    {
        $base = Str::slug($title) ?: 'playlist';
        $slug = $base;
        $counter = 2;
        while (VideoPlaylist::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
