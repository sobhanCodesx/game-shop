<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface TrackedGameSourceAdapter
{
    public function source(): string;

    public function supports(\App\Models\GameSourceState $state): bool;

    /**
     * Fetch fresh observations for already-known source identities.
     *
     * @param Collection<int, \App\Models\GameSourceState> $states
     * @return array<int, array<string, mixed>>
     */
    public function fetch(Collection $states): array;
}
