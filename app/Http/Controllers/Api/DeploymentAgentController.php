<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeploymentAgentChunkRequest;
use App\Services\Deployment\DeploymentManager;
use App\Services\Deployment\DeploymentStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentAgentController extends Controller
{
    private const AGENT_ACTOR_ID = 0;

    public function chunk(DeploymentAgentChunkRequest $request, DeploymentManager $manager): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $manager->acceptChunk(self::AGENT_ACTOR_ID, $data, $request->file('chunk')->getPathname())
        );
    }

    public function complete(Request $request, DeploymentManager $manager): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
        ]);

        return response()->json(
            $manager->complete($data['operation_id'], self::AGENT_ACTOR_ID)
        );
    }

    public function verify(Request $request, DeploymentManager $manager): JsonResponse
    {
        return response()->json(
            $manager->verify((string) $request->route('deployment'), self::AGENT_ACTOR_ID)
        );
    }

    public function apply(Request $request, DeploymentManager $manager): JsonResponse
    {
        return response()->json(
            $manager->advance((string) $request->route('deployment'), self::AGENT_ACTOR_ID)
        );
    }

    public function status(Request $request, DeploymentStateStore $states): JsonResponse
    {
        $state = $states->get((string) $request->route('deployment'));
        abort_unless((int) ($state['user_id'] ?? -1) === self::AGENT_ACTOR_ID, 404);

        return response()->json($state);
    }
}
