<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NexusAi\NexusAiRouter;
use Illuminate\Http\JsonResponse;

final class NexusAiHealthController extends Controller
{
    public function __invoke(NexusAiRouter $router): JsonResponse
    {
        return response()->json($router->health());
    }
}
