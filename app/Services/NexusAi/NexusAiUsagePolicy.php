<?php

namespace App\Services\NexusAi;

use App\Services\NexusAiSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class NexusAiUsagePolicy
{
    public function __construct(
        private readonly NexusAiSettings $settings,
        private readonly NexusAiIdentity $identity,
    ) {
    }

    public function inspect(Request $request, ?string $visitorId): array
    {
        $config = $this->settings->all();
        $userId = $request->user()?->id;
        $limit = $userId
            ? (int) ($config['nexus_ai_user_daily_limit'] ?? 30)
            : (int) ($config['nexus_ai_guest_daily_limit'] ?? 10);

        if ($limit <= 0) {
            return ['allowed' => true, 'limit' => 0, 'used' => 0, 'remaining' => null, 'key' => null];
        }

        $identity = $userId
            ? 'u:'.$userId
            : 'v:'.($this->identity->visitorHash($visitorId) ?? sha1((string) $request->ip()));

        $key = 'nexus-ai:daily:'.now()->format('Y-m-d').':'.$identity;
        $used = $this->get($key);

        return [
            'allowed' => $used < $limit,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'key' => $key,
        ];
    }

    public function consume(array $inspection): int|null
    {
        $key = $inspection['key'] ?? null;
        $limit = (int) ($inspection['limit'] ?? 0);
        if (! is_string($key) || $key === '' || $limit <= 0) {
            return null;
        }

        try {
            Cache::add($key, 0, now()->endOfDay()->addMinute());
            $used = (int) Cache::increment($key);

            return max(0, $limit - $used);
        } catch (Throwable) {
            return max(0, $limit - ((int) ($inspection['used'] ?? 0) + 1));
        }
    }

    private function get(string $key): int
    {
        try {
            return (int) Cache::get($key, 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
