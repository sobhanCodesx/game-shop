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
        'studio_opened',
        'collection_opened',
        'content_opened',
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
            'payload.href' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['interaction_id'])) {
            NexusAiInteraction::query()->whereKey($data['interaction_id'])->firstOrFail();
        }

        $eventType = $data['event_type'];
        $entity = $data['entity'] ?? [];

        if ($eventType === 'link_clicked' && filled($data['payload']['href'] ?? null)) {
            $derived = $this->deriveLinkEntity((string) $data['payload']['href']);
            if ($derived !== null) {
                $eventType = $derived['event_type'];
                $entity = [
                    'type' => $derived['type'],
                    'slug' => $derived['slug'],
                ];
            }
        }

        $analytics->recordEvent(
            $request,
            $eventType,
            $data['interaction_id'] ?? null,
            $data['conversation_id'] ?? null,
            $data['visitor_id'] ?? null,
            $entity,
            $data['payload'] ?? [],
        );

        return response()->json(['saved' => true]);
    }

    private function deriveLinkEntity(string $href): ?array
    {
        $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
        $path = '/'.ltrim($path, '/');

        $patterns = [
            '#^/products/([^/]+)#' => ['event_type' => 'product_opened', 'type' => 'product'],
            '#^/channels/([^/]+)#' => ['event_type' => 'game_opened', 'type' => 'game'],
            '#^/studios/([^/]+)#' => ['event_type' => 'studio_opened', 'type' => 'studio'],
            '#^/collections/([^/]+)#' => ['event_type' => 'collection_opened', 'type' => 'collection'],
            '#^/videos/([^/]+)#' => ['event_type' => 'video_opened', 'type' => 'video'],
            '#^/shorts/([^/]+)#' => ['event_type' => 'content_opened', 'type' => 'short'],
            '#^/posts/([^/]+)#' => ['event_type' => 'content_opened', 'type' => 'post'],
        ];

        foreach ($patterns as $pattern => $meta) {
            if (preg_match($pattern, $path, $matches) === 1) {
                return [
                    ...$meta,
                    'slug' => urldecode((string) $matches[1]),
                ];
            }
        }

        return null;
    }
}
