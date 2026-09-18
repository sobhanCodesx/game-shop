<?php

namespace App\Http\Controllers;

use App\Services\GameRadarService;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class GameRadarController extends Controller
{
    public function index(GameRadarService $radar): Response
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $canonical = route('game-radar.index');
        $image = url((string) config('seo.default_image', '/logo.png'));

        return Inertia::render('GameRadar/Index', [
            'seo' => Seo::page([
                'title' => "رادار بازی‌های جدید و در راه | {$siteName}",
                'description' => "بازی‌های تازه و در راه Xbox را همراه با وضعیت حضور در PlayStation Store، تاریخ انتشار و لینک فروشگاه‌ها در رادار {$siteName} دنبال کنید.",
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'website',
                'siteName' => $siteName,
                'locale' => (string) config('seo.locale', 'fa-IR'),
                'image' => $image,
                'imageAlt' => "Game Radar {$siteName}",
            ]),
            'radar' => $radar->cachedSnapshot(),
        ]);
    }

    public function data(GameRadarService $radar): JsonResponse
    {
        return response()->json($radar->snapshot());
    }
}
