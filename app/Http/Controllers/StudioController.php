<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Services\MediaStorage;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    public function index(): Response
    {
        $studios = Studio::query()->where('status', 'active')
            ->withCount(['games' => fn ($query) => $query->whereIn('status', ['active', 'published'])])
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

    public function show(Studio $studio): Response
    {
        abort_unless($studio->status === 'active', 404);
        $channels = Game::query()->whereBelongsTo($studio)->whereIn('status', ['active', 'published'])
            ->withCount([
                'videos' => fn ($query) => $query->published(),
                'subscribers',
            ])
            ->orderByDesc('subscribers_count')->latest('id')->paginate(18, ['*'], 'channels_page')->withQueryString()
            ->through(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'url' => route('channels.show', $game->slug, false),
                'logo_url' => MediaStorage::url($game->cover),
                'background_url' => MediaStorage::url($game->background),
                'videos_count' => $game->videos_count,
                'followers_count' => $game->subscribers_count,
            ]);
        $collections = VideoPlaylist::query()->whereBelongsTo($studio)->publiclyVisible()
            ->whereHas('game', fn ($query) => $query->whereIn('status', ['active', 'published']))
            ->with('game:id,name,slug,cover')->withCount('videos')
            ->orderBy('sort_order')->latest('id')->paginate(12, ['*'], 'collections_page')->withQueryString()
            ->through(fn (VideoPlaylist $playlist) => [
                'id' => $playlist->id,
                'title' => $playlist->title,
                'description' => RichText::plainText($playlist->description),
                'url' => route('channels.playlists.show', [$playlist->game->slug, $playlist->slug], false),
                'logo_url' => MediaStorage::url($playlist->logo ?: $playlist->game->cover),
                'channel_name' => $playlist->game->name,
                'videos_count' => $playlist->videos_count,
            ]);
        $canonical = route('studios.show', $studio->slug);
        $plainDescription = RichText::plainText($studio->description);

        return Inertia::render('Studios/Show', [
            ...Seo::page([
                'title' => $studio->name,
                'description' => Str::limit($plainDescription ?: "کانال‌ها و بازی‌های استودیو {$studio->name} در PlayNexus.", 160, '…'),
                'canonical' => $canonical,
                'image' => url(MediaStorage::url($studio->background ?: $studio->logo) ?: (string) config('seo.default_image', '/logo.png')),
                'imageAlt' => "استودیو {$studio->name}",
                'type' => 'profile',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => $studio->name,
                    'url' => $canonical,
                    ...($studio->website ? ['sameAs' => [$studio->website]] : []),
                    ...($studio->logo ? ['logo' => url(MediaStorage::url($studio->logo))] : []),
                ],
            ]),
            'studio' => [
                ...$this->studioData($studio),
                'description_html' => RichText::sanitize($studio->description),
                'website' => $studio->website,
            ],
            'channels' => $channels,
            'collections' => $collections,
        ]);
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
        ];
    }
}
