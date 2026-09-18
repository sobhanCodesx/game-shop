<?php

use App\Http\Controllers\Api\ContentAgentMcpController;
use App\Http\Controllers\Api\ExpoPushTokenProxyController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', ContentAgentMcpController::class)
    ->middleware(['content.agent', 'throttle:60,1'])
    ->name('content-agent.mcp');

Route::post('/mobile/push/expo-token', ExpoPushTokenProxyController::class)
    ->middleware('throttle:30,1')
    ->name('mobile.push.expo-token');
