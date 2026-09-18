<?php

use App\Http\Controllers\VideoProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:120,1'])
    ->prefix('watch-progress')
    ->name('watch-progress.')
    ->group(function (): void {
        Route::get('/', [VideoProgressController::class, 'index'])->name('index');
        Route::post('{content}', [VideoProgressController::class, 'store'])
            ->whereNumber('content')
            ->name('store');
    });
