<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NexusAiInteraction;
use App\Services\NexusAi\NexusAiAnalyticsService;
use App\Services\NexusAiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NexusAiEventController extends Controller
{
    private const ALLOWED_EVENTS = [
        'answer_copied',
        'link_clicked',
        'followup_sent',
        'game_opened',
        'video_opened',
        'product_opened',
        'conversation_cleared',
    ];

    public function __invoke(
        Request $request,
        NexusAiAnalyticsService $analytics,
        NexusAiSettings $settings,
    ): JsonResponse {
        if (! (bool) ($settings->all()['nexus_ai_collect_analytics'] ?? true)) {
            return response()->json(['saved' => false]);
        }
        $data = $request->validate([
            'event_type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_EVENTS)],
            'interaction_id' => ['nullable', 'integer', 'min:1'],
            'conversation_id' => ['nullable', 'uuid'],
            'visitor_id' => ['nullable', 'uuid'],
            'entity' => ['nullable', 'array'],
            'entity.type' => ['nullable', 'string', 'max:48'],
            'entity.id' => ['nullable', 'integer', 'min:1'],
            'entity.slug' => ['nullable', 'string', 'max:190'],
            'payload' => ['nullable', 'array'],
        ]);

        if (isset($data['interaction_id'])) {
            NexusAiInteraction::query()->whereKey($data['interaction_id'])->firstOrFail();
        }

        $analytics->recordEvent(
            $request,
            $data['event_type'],
            $data['interaction_id'] ?? null,
            $data['conversation_id'] ?? null,
            $data['visitor_id'] ?? null,
            $data['entity'] ?? [],
            $data['payload'] ?? [],
        );

        return response()->json(['saved' => true]);
    }
}
