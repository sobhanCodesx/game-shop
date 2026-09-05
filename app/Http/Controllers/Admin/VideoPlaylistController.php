<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoPlaylistRequest;
use App\Models\Game;
use App\Models\VideoPlaylist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoPlaylistController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Playlists/Index', [
            'playlists' => VideoPlaylist::query()->with('game:id,name')->withCount('videos')
                ->orderBy('sort_order')->latest('id')->get()->map(fn (VideoPlaylist $playlist) => [
                    ...$playlist->only(['id', 'game_id', 'title', 'description', 'visibility', 'sort_order']),
                    'game' => $playlist->game?->name,
                    'videos_count' => $playlist->videos_count,
                ]),
            'games' => Game::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(VideoPlaylistRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug((int) $data['game_id'], $data['title']);
        VideoPlaylist::query()->create($data);

        return back()->with('success', 'کالکشن ویدیو ساخته شد.');
    }

    public function update(VideoPlaylistRequest $request, VideoPlaylist $playlist): RedirectResponse
    {
        $data = $request->validated();
        if ($playlist->title !== $data['title'] || $playlist->game_id !== (int) $data['game_id']) {
            $data['slug'] = $this->uniqueSlug((int) $data['game_id'], $data['title'], $playlist->id);
        }
        $playlist->update($data);

        return back()->with('success', 'کالکشن به‌روزرسانی شد.');
    }

    public function destroy(VideoPlaylist $playlist): RedirectResponse
    {
        $playlist->delete();

        return back()->with('success', 'کالکشن حذف شد.');
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
