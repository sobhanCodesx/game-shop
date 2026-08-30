<?php

namespace App\Services;

use App\Models\HomeSetting;

final class CommerceSettings
{
    public function all(): array
    {
        $content = HomeSetting::query()->first()?->content ?? [];

        return [
            'delivery_fee' => max(0, (int) ($content['delivery_fee'] ?? 0)),
            'cashback_percent' => min(100, max(0, (float) ($content['cashback_percent'] ?? 2))),
        ];
    }
}
