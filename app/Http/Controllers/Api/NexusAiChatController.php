<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NexusAi\NexusAiAnalyticsService;
use App\Services\NexusAi\NexusAiIntentClassifier;
use App\Services\NexusAi\NexusAiRouter;
use App\Services\NexusAi\NexusAiUsagePolicy;
use App\Services\NexusAiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

final class NexusAiChatController extends Controller
{
    public function __invoke(
        Request $request,
        NexusAiRouter $router,
        NexusAiUsagePolicy $usage,
        NexusAiAnalyticsService $analytics,
        NexusAiIntentClassifier $intent,
        NexusAiSettings $settings,
    ): JsonResponse {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1600'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:3000'],
            'conversation_id' => ['nullable', 'uuid'],
            'visitor_id' => ['nullable', 'uuid'],
        ]);

        $message = trim($data['message']);
        $history = is_array($data['history'] ?? null) ? $data['history'] : [];
        $conversationId = (string) ($data['conversation_id'] ?? Str::uuid());
        $visitorId = isset($data['visitor_id']) ? (string) $data['visitor_id'] : null;
        $inspection = $usage->inspect($request, $visitorId);

        if (! $inspection['allowed']) {
            return response()->json([
                'error' => 'daily_limit_reached',
                'limit' => $inspection['limit'],
                'remaining_today' => 0,
            ], 429);
        }

        $startedAt = hrtime(true);

        try {
            $result = $router->chat($message, $history);
        } catch (RuntimeException) {
            $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            if ((bool) ($settings->all()['nexus_ai_collect_analytics'] ?? true)) {
                $analytics->recordInteraction(
                    $request,
                    $conversationId,
                    $visitorId,
                    $message,
                    ['intent' => $intent->classify($message)],
                    'failed',
                    $latencyMs,
                    'upstream_unavailable',
                );
            }

            return response()->json(['error' => 'upstream_unavailable'], 503);
        }

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $remaining = $usage->consume($inspection);
        $interaction = null;

        if ((bool) ($settings->all()['nexus_ai_collect_analytics'] ?? true)) {
            $interaction = $analytics->recordInteraction(
                $request,
                $conversationId,
                $visitorId,
                $message,
                $result,
                'success',
                $latencyMs,
            );
        }

        return response()->json([
            'answer' => $result['answer'],
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'intent' => $result['intent'] ?? null,
            'fallback_count' => (int) ($result['fallback_count'] ?? 0),
            'interaction_id' => $interaction?->id,
            'conversation_id' => $conversationId,
            'remaining_today' => $remaining,
        ]);
    }
}
