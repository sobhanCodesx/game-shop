<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NexusAi\NexusAiRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class NexusAiChatController extends Controller
{
    public function __invoke(Request $request, NexusAiRouter $router): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1600'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:3000'],
        ]);

        try {
            $result = $router->chat(
                trim($data['message']),
                is_array($data['history'] ?? null) ? $data['history'] : [],
            );
        } catch (RuntimeException) {
            return response()->json(['error' => 'upstream_unavailable'], 503);
        }

        return response()->json($result);
    }
}
