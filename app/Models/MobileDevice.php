<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDevice extends Model
{
    protected $fillable = [
        'user_id', 'installation_id', 'push_token', 'push_provider', 'platform', 'device_name',
        'app_version', 'push_enabled', 'failure_count', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
