<?php

use App\Http\Controllers\Api\ContentAgentMcpController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', ContentAgentMcpController::class)
    ->middleware(['content.agent', 'throttle:60,1'])
    ->name('content-agent.mcp');
