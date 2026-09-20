<?php

use App\Services\FollowedGameWatchService;
use App\Services\GameEventService;
use App\Services\GameRadarService;
use App\Services\GameSourceMonitorService;
use App\Services\MobileApiTokenService;
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

Artisan::command('nexus:detect-game-source-changes', function () {
    $snapshot = app(GameRadarService::class)->linkedSnapshot();
    $stats = app(GameSourceMonitorService::class)->observeRadarSnapshot($snapshot);

    $this->info(sprintf(
        'Game source monitor: %d observed, %d baselines, %d changed, %d events.',
        $stats['observed'],
        $stats['baselines'],
        $stats['changed'],
        $stats['events'],
    ));
})->purpose('Detect trustworthy changes from the cached, linked Game Radar snapshot');

Artisan::command('nexus:sync-followed-game-watchlist', function () {
    $stats = app(FollowedGameWatchService::class)->sync();

    $this->info(sprintf(
        'Nexus Watch: %d games, %d direct sources, %d observations, %d changes, %d events.',
        $stats['games'],
        $stats['sources'],
        $stats['observed'],
        $stats['changed'],
        $stats['events'],
    ));
})->purpose('Directly monitor known official sources for distinct followed games');

Artisan::command('nexus:sync-game-events {--days=90}', function () {
    $days = max(1, min(365, (int) $this->option('days')));
    $count = app(GameEventService::class)->syncRecentContent($days);

    $this->info("Game Events synced: {$count} records considered from the last {$days} days.");
})->purpose('Backfill canonical Game Events from published PlayNexus content');

Artisan::command('nexus:prune-mobile-api-tokens', function () {
    $count = app(MobileApiTokenService::class)->pruneExpired();
    $this->info("Expired mobile API tokens pruned: {$count}.");
})->purpose('Delete expired native mobile API access tokens');

Schedule::command('nexus:prune-mobile-api-tokens')
    ->dailyAt('03:20')
    ->withoutOverlapping();

Schedule::command('nexus:sync-game-radar')
    ->everySixHours()
    ->withoutOverlapping();


Schedule::command('nexus:sync-game-events --days=7')
    ->daily()
    ->withoutOverlapping();


Schedule::command('nexus:sync-followed-game-watchlist')
    ->cron('20 */6 * * *')
    ->withoutOverlapping();
