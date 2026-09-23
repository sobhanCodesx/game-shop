<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NexusAi\NexusAiContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NexusAiContextController extends Controller
{
    public function __invoke(Request $request, NexusAiContextService $context): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:800'],
        ]);

        return response()->json($context->build(trim($data['question'])));
    }
}
