<?php

namespace App\Contracts;

interface GameSourceAdapter
{
    /**
     * Normalize a source payload into stable observations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function observations(array $payload): array;
}
