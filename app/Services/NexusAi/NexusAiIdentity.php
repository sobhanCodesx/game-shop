<?php

namespace App\Services\NexusAi;

final class NexusAiIdentity
{
    public function visitorHash(?string $visitorId): ?string
    {
        $visitorId = trim((string) $visitorId);
        if ($visitorId === '') {
            return null;
        }

        return hash_hmac('sha256', $visitorId, (string) config('app.key'));
    }
}
