<?php

namespace App\Http\Controllers;

use App\Services\GameRadarService;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GameRadarController extends Controller
{
    public function index(GameRadarService $radar): Response
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('game-radar.index');
        $snapshot = $radar->cachedSnapshot();
        $items = collect($snapshot['items'] ?? [])->values();

        $featured = $items->first();
        $featuredImage = is_array($featured)
            ? ($featured['banner_url'] ?? $featured['cover_url'] ?? $featured['psn']['image_url'] ?? null)
            : null;

        $image = filled($featuredImage)
            ? (Str::startsWith((string) $featuredImage, ['http://', 'https://'])
                ? (string) $featuredImage
                : url((string) $featuredImage))
            : url((string) config('seo.default_image', '/logo.png'));

        $title = "بازی‌های جدید PS5 و Xbox | تاریخ انتشار | {$siteName}";
        $description = "جدیدترین بازی‌های PS5 و Xbox، بازی‌های در راه، تاریخ انتشار، پلتفرم و وضعیت حضور در PlayStation Store و Xbox Store را در Game Radar {$siteName} دنبال کنید.";

        $itemList = $items
            ->take(24)
            ->map(function (array $item, int $index) {
                $platforms = collect();

                if (($item['xbox']['available'] ?? false) === true) {
                    $platforms->push('Xbox Series X|S');
                }

                if (($item['psn']['available'] ?? false) === true) {
                    $psPlatforms = collect($item['psn']['platforms'] ?? [])
                        ->filter(fn ($platform) => is_string($platform) && $platform !== '');

                    if ($psPlatforms->isNotEmpty()) {
                        $platforms = $platforms->concat($psPlatforms);
                    } else {
                        $platforms->push('PlayStation 5');
                    }
                }

                $sameAs = collect([
                    $item['xbox']['url'] ?? null,
                    $item['psn']['url'] ?? null,
                ])->filter()->values()->all();

                $game = array_filter([
                    '@type' => 'VideoGame',
                    'name' => (string) ($item['title'] ?? ''),
                    'description' => filled($item['description'] ?? null)
                        ? (string) $item['description']
                        : null,
                    'image' => $item['cover_url'] ?? $item['banner_url'] ?? $item['psn']['image_url'] ?? null,
                    'datePublished' => $item['release_date'] ?? null,
                    'gamePlatform' => $platforms->unique()->values()->all(),
                    'publisher' => filled($item['publisher'] ?? null)
                        ? [
                            '@type' => 'Organization',
                            'name' => (string) $item['publisher'],
                        ]
                        : null,
                    'sameAs' => $sameAs !== [] ? $sameAs : null,
                ], fn ($value) => $value !== null && $value !== '' && $value !== []);

                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $game,
                ];
            })
            ->all();

        return Inertia::render('GameRadar/Index', [
            'seo' => Seo::page([
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'website',
                'siteName' => $siteName,
                'locale' => $locale,
                'image' => $image,
                'imageAlt' => filled($featured['title'] ?? null)
                    ? "بازی {$featured['title']} در Game Radar {$siteName}"
                    : "بازی‌های جدید PS5 و Xbox در {$siteName}",
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            '@id' => $canonical.'#webpage',
                            'url' => $canonical,
                            'name' => $title,
                            'description' => $description,
                            'inLanguage' => $locale,
                            'mainEntity' => [
                                '@id' => $canonical.'#games',
                            ],
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                [
                                    '@type' => 'ListItem',
                                    'position' => 1,
                                    'name' => 'خانه',
                                    'item' => route('home'),
                                ],
                                [
                                    '@type' => 'ListItem',
                                    'position' => 2,
                                    'name' => 'بازی‌های جدید PS5 و Xbox',
                                    'item' => $canonical,
                                ],
                            ],
                        ],
                        [
                            '@type' => 'ItemList',
                            '@id' => $canonical.'#games',
                            'name' => 'بازی‌های جدید و در راه PS5 و Xbox',
                            'numberOfItems' => count($itemList),
                            'itemListElement' => $itemList,
                        ],
                    ],
                ],
            ]),
            'radar' => $snapshot,
        ]);
    }

    public function data(GameRadarService $radar): JsonResponse
    {
        return response()
            ->json($radar->snapshot())
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
