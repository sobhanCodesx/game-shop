<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NexusAiInteraction;
use App\Services\NexusAi\NexusAiAnalyticsService;
use App\Services\NexusAi\NexusAiIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NexusAiFeedbackController extends Controller
{
    public function __invoke(
        Request $request,
        NexusAiAnalyticsService $analytics,
        NexusAiIdentity $identity,
    ): JsonResponse {
        $data = $request->validate([
            'interaction_id' => ['required', 'integer', 'min:1'],
            'conversation_id' => ['required', 'uuid'],
            'visitor_id' => ['nullable', 'uuid'],
            'value' => ['required', 'integer', 'in:-1,1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $interaction = NexusAiInteraction::query()
            ->whereKey($data['interaction_id'])
            ->where('conversation_id', $data['conversation_id'])
            ->firstOrFail();

        $userId = $request->user()?->id;
        $visitorHash = $identity->visitorHash($data['visitor_id'] ?? null);
        $ownsInteraction = ($userId && (int) $interaction->user_id === (int) $userId)
            || ($visitorHash && hash_equals((string) $interaction->visitor_hash, $visitorHash));

        abort_unless($ownsInteraction, 403);

        $analytics->recordFeedback(
            $request,
            $interaction,
            (int) $data['value'],
            $data['reason'] ?? null,
        );

        return response()->json(['saved' => true]);
    }
}
