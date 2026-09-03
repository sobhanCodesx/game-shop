<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class SmsProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $providerStatus = null,
        public readonly ?array $providerResponse = null,
        public readonly bool $retryable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
