<?php

namespace App\Services\NexusAi;

use App\Models\NexusAiKnowledge;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class NexusAiKnowledgeService
{
    private const MAX_PROMPT_CHARS = 7000;

    public function promptContext(): string
    {
        if (! $this->available()) {
            return '';
        }

        try {
            $items = NexusAiKnowledge::query()
                ->where('enabled', true)
                ->orderBy('priority')
                ->orderByDesc('updated_at')
                ->limit(40)
                ->get(['title', 'type', 'content']);

            $chunks = [];
            $used = 0;

            foreach ($items as $item) {
                $chunk = sprintf(
                    "[%s] %s\n%s",
                    strtoupper((string) $item->type),
                    trim((string) $item->title),
                    trim((string) $item->content),
                );

                $remaining = self::MAX_PROMPT_CHARS - $used;
                if ($remaining <= 0) {
                    break;
                }

                $chunk = mb_substr($chunk, 0, $remaining);
                $chunks[] = $chunk;
                $used += mb_strlen($chunk) + 2;
            }

            return implode("\n\n", $chunks);
        } catch (Throwable) {
            return '';
        }
    }

    public function adminList(): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            return NexusAiKnowledge::query()
                ->orderBy('priority')
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get()
                ->map(fn (NexusAiKnowledge $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'type' => $item->type,
                    'content' => $item->content,
                    'enabled' => $item->enabled,
                    'priority' => $item->priority,
                    'updated_at' => $item->updated_at?->toIso8601String(),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('nexus_ai_knowledge');
        } catch (Throwable) {
            return false;
        }
    }
}
