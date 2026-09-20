<?php

use App\Http\Controllers\AndroidAppController;
use Illuminate\Support\Facades\Route;

Route::get('download/android', AndroidAppController::class)
    ->middleware('throttle:30,1')
    ->name('android.apk.download');
