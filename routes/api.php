<?php

use App\Http\Controllers\Api\ContentAgentGraphqlController;
use App\Http\Controllers\Api\ContentAgentMcpController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', ContentAgentMcpController::class)
    ->middleware(['content.agent', 'throttle:60,1'])
    ->name('content-agent.mcp');


Route::post('/graphql', ContentAgentGraphqlController::class)
    ->middleware(['content.agent', 'throttle:30,1'])
    ->name('content-agent.graphql');
