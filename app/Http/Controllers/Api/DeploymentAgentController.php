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
    public function chunk(DeploymentAgentChunkRequest $request, DeploymentManager $manager): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $manager->acceptChunk($this->actorId(), $data, $request->file('chunk')->getPathname())
        );
    }

    public function complete(Request $request, DeploymentManager $manager): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
        ]);

        return response()->json(
            $manager->complete($data['operation_id'], $this->actorId())
        );
    }

    public function verify(Request $request, DeploymentManager $manager): JsonResponse
    {
        return response()->json(
            $manager->verify((string) $request->route('deployment'), $this->actorId())
        );
    }

    public function apply(Request $request, DeploymentManager $manager): JsonResponse
    {
        return response()->json(
            $manager->advance((string) $request->route('deployment'), $this->actorId())
        );
    }

    public function status(Request $request, DeploymentStateStore $states): JsonResponse
    {
        $state = $states->get((string) $request->route('deployment'));
        abort_unless((int) ($state['user_id'] ?? -1) === $this->actorId(), 404);

        return response()->json($state);
    }

    private function actorId(): int
    {
        $id = (int) config('content_agent.author_user_id');
        abort_if($id < 1, 503, 'PlayNexus content agent author is not configured.');

        return $id;
    }
}
