<?php

namespace App\Services\NexusAi;

use App\Models\NexusAiEvent;
use App\Models\NexusAiFeedback;
use App\Models\NexusAiInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class NexusAiAnalyticsService
{
    public function __construct(private readonly NexusAiIdentity $identity)
    {
    }

    public function recordInteraction(
        Request $request,
        string $conversationId,
        ?string $visitorId,
        string $question,
        ?array $result,
        string $status,
        int $latencyMs,
        ?string $error = null,
    ): ?NexusAiInteraction {
        if (! $this->available('nexus_ai_interactions')) {
            return null;
        }

        try {
            return NexusAiInteraction::query()->create([
                'conversation_id' => $conversationId,
                'user_id' => $request->user()?->id,
                'visitor_hash' => $this->identity->visitorHash($visitorId),
                'question' => $question,
                'answer' => $result['answer'] ?? null,
                'intent' => $result['intent'] ?? 'general',
                'entities' => $result['context_terms'] ?? [],
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
                'latency_ms' => $latencyMs,
                'context_chars' => (int) ($result['context_chars'] ?? 0),
                'status' => $status,
                'meta' => array_filter([
                    'error' => $error,
                    'fallback_count' => $result['fallback_count'] ?? null,
                ], fn ($value) => $value !== null),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function recordFeedback(Request $request, NexusAiInteraction $interaction, int $value, ?string $reason): void
    {
        if (! $this->available('nexus_ai_feedback')) {
            return;
        }

        try {
            NexusAiFeedback::query()->updateOrCreate(
                ['interaction_id' => $interaction->id],
                [
                    'user_id' => $request->user()?->id,
                    'value' => $value > 0 ? 1 : -1,
                    'reason' => filled($reason) ? trim((string) $reason) : null,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function recordEvent(
        Request $request,
        string $eventType,
        ?int $interactionId,
        ?string $conversationId,
        ?string $visitorId,
        array $entity = [],
        array $payload = [],
    ): void {
        if (! $this->available('nexus_ai_events')) {
            return;
        }

        try {
            NexusAiEvent::query()->create([
                'interaction_id' => $interactionId,
                'user_id' => $request->user()?->id,
                'conversation_id' => $conversationId,
                'visitor_hash' => $this->identity->visitorHash($visitorId),
                'event_type' => $eventType,
                'entity_type' => $entity['type'] ?? null,
                'entity_id' => isset($entity['id']) ? (int) $entity['id'] : null,
                'entity_slug' => $entity['slug'] ?? null,
                'payload' => $payload === [] ? null : $payload,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function dashboard(): array
    {
        if (! $this->available('nexus_ai_interactions')) {
            return $this->emptyDashboard();
        }

        try {
            $today = now()->startOfDay();
            $todayQuery = NexusAiInteraction::query()->where('created_at', '>=', $today);
            $todayCount = (clone $todayQuery)->count();
            $successCount = (clone $todayQuery)->where('status', 'success')->count();
            $avgLatency = (int) round((float) ((clone $todayQuery)
                ->whereNotNull('latency_ms')
                ->avg('latency_ms') ?? 0));

            $freeProviders = [
                NexusAiProviderSettings::CLOUDFLARE_WORKERS_AI,
                NexusAiProviderSettings::GROQ,
                NexusAiProviderSettings::LOCAL_RELAY,
            ];
            $freeCount = (clone $todayQuery)
                ->where('status', 'success')
                ->whereIn('provider', $freeProviders)
                ->count();

            $feedback = ['positive' => 0, 'negative' => 0, 'positive_rate' => null];
            if ($this->available('nexus_ai_feedback')) {
                $positive = NexusAiFeedback::query()->where('created_at', '>=', $today)->where('value', 1)->count();
                $negative = NexusAiFeedback::query()->where('created_at', '>=', $today)->where('value', -1)->count();
                $feedback = [
                    'positive' => $positive,
                    'negative' => $negative,
                    'positive_rate' => ($positive + $negative) > 0
                        ? round(($positive / ($positive + $negative)) * 100, 1)
                        : null,
                ];
            }

            $intents = NexusAiInteraction::query()
                ->select('intent', DB::raw('COUNT(*) as total'))
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('intent')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($row): array => ['intent' => $row->intent, 'total' => (int) $row->total])
                ->all();

            $providers = NexusAiInteraction::query()
                ->select('provider', DB::raw('COUNT(*) as total'), DB::raw('AVG(latency_ms) as avg_latency'))
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status', 'success')
                ->whereNotNull('provider')
                ->groupBy('provider')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row): array => [
                    'provider' => $row->provider,
                    'total' => (int) $row->total,
                    'avg_latency' => (int) round((float) $row->avg_latency),
                ])
                ->all();

            $recent = NexusAiInteraction::query()
                ->latest('id')
                ->limit(12)
                ->get(['id', 'question', 'intent', 'provider', 'model', 'latency_ms', 'status', 'created_at'])
                ->map(fn (NexusAiInteraction $item): array => [
                    'id' => $item->id,
                    'question' => $item->question,
                    'intent' => $item->intent,
                    'provider' => $item->provider,
                    'model' => $item->model,
                    'latency_ms' => $item->latency_ms,
                    'status' => $item->status,
                    'created_at' => $item->created_at?->toIso8601String(),
                ])
                ->all();

            return [
                'today' => [
                    'messages' => $todayCount,
                    'success_rate' => $todayCount > 0 ? round(($successCount / $todayCount) * 100, 1) : 100.0,
                    'avg_latency' => $avgLatency,
                    'free_rate' => $successCount > 0 ? round(($freeCount / $successCount) * 100, 1) : 100.0,
                    'unique_users' => (clone $todayQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                    'unique_visitors' => (clone $todayQuery)->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
                ],
                'feedback' => $feedback,
                'intents' => $intents,
                'providers' => $providers,
                'recent' => $recent,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyDashboard();
        }
    }

    private function emptyDashboard(): array
    {
        return [
            'today' => [
                'messages' => 0,
                'success_rate' => 100.0,
                'avg_latency' => 0,
                'free_rate' => 100.0,
                'unique_users' => 0,
                'unique_visitors' => 0,
            ],
            'feedback' => ['positive' => 0, 'negative' => 0, 'positive_rate' => null],
            'intents' => [],
            'providers' => [],
            'recent' => [],
        ];
    }

    private function available(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
