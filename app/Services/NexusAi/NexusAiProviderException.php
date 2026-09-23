<?php

namespace App\Services\NexusAi;

use RuntimeException;

final class NexusAiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
