<?php

use App\Services\GameEventService;
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

Artisan::command('nexus:sync-game-events {--days=90}', function () {
    $days = max(1, min(365, (int) $this->option('days')));
    $count = app(GameEventService::class)->syncRecentContent($days);

    $this->info("Game Events synced: {$count} records considered from the last {$days} days.");
})->purpose('Backfill canonical Game Events from published PlayNexus content');

Schedule::command('nexus:sync-game-radar')
    ->everySixHours()
    ->withoutOverlapping();


Schedule::command('nexus:sync-game-events --days=7')
    ->daily()
    ->withoutOverlapping();
