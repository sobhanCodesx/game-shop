<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GraphQL\PlayNexusGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ContentAgentGraphqlController extends Controller
{
    public function __invoke(Request $request, PlayNexusGraphService $graph): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === [] || array_is_list($payload)) {
            return response()->json(['errors' => [['message' => 'Invalid GraphQL request body.']]], 400);
        }

        $query = is_string($payload['query'] ?? null) ? $payload['query'] : '';
        $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $operationName = is_string($payload['operationName'] ?? null)
            ? $payload['operationName']
            : null;

        try {
            return response()->json($graph->execute($query, $variables, $operationName));
        } catch (RuntimeException $exception) {
            return response()->json([
                'errors' => [['message' => $exception->getMessage()]],
            ], 400);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'errors' => [['message' => 'The PlayNexus Intelligence Graph could not complete the request.']],
            ], 500);
        }
    }
}
