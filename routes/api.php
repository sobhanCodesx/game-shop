<?php

use App\Http\Controllers\Api\ContentAgentGraphqlController;
use App\Http\Controllers\Api\ContentAgentMcpController;
use App\Http\Controllers\Api\DeploymentAgentController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', ContentAgentMcpController::class)
    ->middleware(['content.agent', 'throttle:60,1'])
    ->name('content-agent.mcp');


Route::post('/graphql', ContentAgentGraphqlController::class)
    ->middleware(['content.agent', 'throttle:30,1'])
    ->name('content-agent.graphql');


Route::prefix('/deployment-agent')
    ->middleware(['throttle:300,1', 'deployment.agent'])
    ->name('deployment-agent.')
    ->group(function () {
        Route::post('/upload/chunk', [DeploymentAgentController::class, 'chunk'])->name('chunk');
        Route::post('/upload/complete', [DeploymentAgentController::class, 'complete'])->name('complete');
        Route::post('/{deployment}/verify', [DeploymentAgentController::class, 'verify'])->whereUuid('deployment')->name('verify');
        Route::post('/{deployment}/apply', [DeploymentAgentController::class, 'apply'])->whereUuid('deployment')->name('apply');
        Route::get('/{deployment}/status', [DeploymentAgentController::class, 'status'])->whereUuid('deployment')->name('status');
    });
