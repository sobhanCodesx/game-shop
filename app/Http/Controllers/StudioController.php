<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Services\MediaStorage;
use App\Services\StudioPageDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    public function index(): Response
    {
        $studios = Studio::query()->where('status', 'active')
            ->withCount([
                'games' => fn ($query) => $query->whereIn('status', ['active', 'published']),
            ])
            ->addSelect(['collections_count' => VideoPlaylist::query()
                ->selectRaw('count(*)')
                ->publiclyVisible()
                ->where(fn ($query) => $query
                    ->whereColumn('video_playlists.studio_id', 'studios.id')
                    ->orWhereHas('game', fn ($gameQuery) => $gameQuery->whereColumn('games.studio_id', 'studios.id')))])
            ->orderByDesc('games_count')->latest('id')->paginate(24)->withQueryString()
            ->through(fn (Studio $studio) => $this->studioData($studio));
        $canonical = route('studios.index');
        $description = 'استودیوها و شرکت‌های بازی‌سازی، همراه با کانال‌ها و بازی‌های مرتبط در PlayNexus.';

        return Inertia::render('Studios/Index', [
            ...Seo::page([
                'title' => 'استودیوها و شرکت‌های بازی‌سازی',
                'description' => $description,
                'canonical' => $canonical,
                'image' => url((string) config('seo.default_image', '/logo.png')),
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'ItemList',
                    'name' => 'استودیوهای بازی‌سازی',
                    'itemListElement' => collect($studios->items())->map(fn (array $studio, int $index) => [
                        '@type' => 'ListItem', 'position' => $index + 1, 'name' => $studio['name'], 'url' => url($studio['url']),
                    ])->all(),
                ],
            ]),
            'studios' => $studios,
        ]);
    }

    public function show(Studio $studio, Request $request, StudioPageDataService $page): Response
    {
        abort_unless($studio->status === 'active', 404);

        return Inertia::render('Studios/Show', $page->get($studio, $request));
    }

    private function studioData(Studio $studio): array
    {
        return [
            'id' => $studio->id,
            'name' => $studio->name,
            'slug' => $studio->slug,
            'url' => route('studios.show', $studio->slug, false),
            'logo_url' => MediaStorage::url($studio->logo),
            'background_url' => MediaStorage::url($studio->background),
            'description' => RichText::plainText($studio->description),
            'channels_count' => (int) ($studio->games_count ?? $studio->games()->whereIn('status', ['active', 'published'])->count()),
            'collections_count' => (int) ($studio->collections_count ?? VideoPlaylist::query()
                ->where(fn ($query) => $query
                    ->where('studio_id', $studio->id)
                    ->orWhereHas('game', fn ($gameQuery) => $gameQuery->where('studio_id', $studio->id)))
                ->publiclyVisible()->count()),
        ];
    }
}
