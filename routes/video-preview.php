<?php

use App\Http\Controllers\VideoPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('video-previews/{content:slug}', VideoPreviewController::class)
    ->middleware('throttle:120,1')
    ->name('video-previews.show');
