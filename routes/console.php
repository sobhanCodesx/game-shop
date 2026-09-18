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
    $count = count($snapshot['items'] ?? []);

    $this->info("Game Radar synced: {$count} titles.");
})->purpose('Refresh the cached PlayNexus Game Radar snapshot');

Schedule::command('nexus:sync-game-radar')
    ->everySixHours()
    ->withoutOverlapping();
