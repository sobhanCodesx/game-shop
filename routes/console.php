<?php

use App\Services\GameRadarService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('nexus:sync-game-radar', function () {
    $snapshot = app(GameRadarService::class)->refresh();
    $items = collect($snapshot['items'] ?? []);
    $count = $items->count();
    $ps5Count = $items->filter(fn (array $item) => ($item['psn']['available'] ?? false) === true)->count();
    $xboxCount = $items->filter(fn (array $item) => ($item['xbox']['available'] ?? false) === true)->count();

    $this->info("Game Radar synced: {$count} titles (PS5: {$ps5Count}, Xbox: {$xboxCount}).");
})->purpose('Refresh the cached PlayNexus Game Radar snapshot');

Schedule::command('nexus:sync-game-radar')
    ->everySixHours()
    ->withoutOverlapping();
