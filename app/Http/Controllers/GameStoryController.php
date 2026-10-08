<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameStory;
use App\Services\MediaStorage;
use App\Services\GameStoryService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GameStoryController extends Controller
{
    public function game(Request $request, Game $game): Response
    {
        abort_unless(in_array($game->status, ['active', 'published'], true), 404);
        $request->merge(['game' => $game->slug]);

        return $this->index($request);
    }

    public function index(Request $request): Response
    {
        $gameSlug = $request->string('game')->toString();
        $kind = $request->string('kind')->toString();
        $game = $gameSlug !== '' ? Game::query()->where('slug', $gameSlug)->whereIn('status', ['active', 'published'])->firstOrFail() : null;

        $stories = GameStory::published()
            ->with('game:id,name,slug,cover,background')
            ->when($game, fn ($q) => $q->where('game_id', $game->id))
            ->when(in_array($kind, GameStory::KINDS, true), fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(12)->withQueryString()
            ->through(fn (GameStory $story) => $story->card());

        $isGameHub = $game && $request->routeIs('game-stories.game');
        $canonical = $isGameHub ? route('game-stories.game', $game->slug) : route('game-stories.index');
        return Inertia::render('GameStories/Index', [
            ...Seo::page([
                'title' => $isGameHub ? 'گیم استوری '. $game->name.' | داستان‌ها و شخصیت‌ها' : 'گیم استوری | روایت‌ها، جهان‌ها و شخصیت‌های بازی‌ها',
                'description' => $isGameHub ? 'روایت‌های کوتاه و اختصاصی از جهان، شخصیت‌ها، داستان و رازهای بازی '.$game->name.'؛ در کتابخانه Game Story پلی نکسوس.' : 'روایت‌های کوتاه از داستان بازی‌ها، شخصیت‌ها، رازهای جهان، نظریه‌ها و شایعه‌های مشخص‌شده؛ در کتابخانه Game Story پلی نکسوس.',
                'canonical' => $canonical,
                'robots' => ($gameSlug && ! $isGameHub) || $kind || $stories->currentPage() > 1 ? 'noindex, follow' : 'index, follow, max-image-preview:large',
                'type' => 'website',
                'image' => $stories->items()[0]['image_url'] ?? url((string) config('seo.default_image', '/logo.png')),
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => 'کتابخانه Game Story',
                    'url' => $canonical,
                    'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => collect($stories->items())->values()->map(fn ($item, $i) => [
                        '@type' => 'ListItem', 'position' => $i + 1, 'url' => url($item['url']),
                    ])->all()],
                ],
            ]),
            'stories' => $stories,
            'filters' => ['game' => $gameSlug, 'kind' => $kind],
            'selectedGame' => $game?->only(['id', 'name', 'slug']),
        ]);
    }

    public function show(GameStory $story, GameStoryService $service): Response
    {
        abort_unless($story->status === 'published' && $story->published_at && $story->published_at->isPast(), 404);
        $story->load('game:id,name,slug,status,cover,background', 'author:id,name');
        abort_unless($story->game && in_array($story->game->status, ['active', 'published'], true), 404);

        $related = GameStory::published()->where('game_id', $story->game_id)->whereKeyNot($story->id)
            ->with('game:id,name,slug,cover,background')->orderByDesc('published_at')->limit(4)->get()
            ->map(fn (GameStory $item) => $item->card())->all();
        $canonical = route('game-stories.show', $story->slug);
        $title = $story->seo_title ?: $story->title;
        $description = Str::limit($story->seo_description ?: $story->summary ?: strip_tags((string) $story->body), 160, '…');
        $image = $story->cover_url ?: url((string) config('seo.default_image', '/logo.png'));

        return Inertia::render('GameStories/Show', [
            ...Seo::page([
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'article',
                'image' => $image,
                'imageAlt' => $story->title,
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [[
                        '@type' => 'Article',
                        '@id' => $canonical.'#article',
                        'mainEntityOfPage' => $canonical,
                        'headline' => $story->title,
                        'description' => $description,
                        'image' => $image,
                        'inLanguage' => 'fa-IR',
                        'datePublished' => $story->published_at?->toAtomString(),
                        'dateModified' => $story->updated_at?->toAtomString(),
                        'author' => ['@type' => 'Organization', 'name' => 'PlayNexus'],
                        'publisher' => ['@type' => 'Organization', 'name' => 'PlayNexus', 'url' => route('home')],
                        'isPartOf' => ['@type' => 'CreativeWorkSeries', 'name' => $story->game->name.' Game Stories'],
                        'about' => ['@type' => 'VideoGame', 'name' => $story->game->name, 'url' => route('channels.show', $story->game->slug)],
                        'isAccessibleForFree' => true,
                    ], [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => 'گیم استوری', 'item' => route('game-stories.index')],
                            ['@type' => 'ListItem', 'position' => 3, 'name' => $story->title, 'item' => $canonical],
                        ],
                    ]],
                ],
            ]),
            'story' => $service->serialize($story),
            'related' => $related,
            'gameUrl' => route('channels.show', $story->game->slug, false),
        ]);
    }
}
