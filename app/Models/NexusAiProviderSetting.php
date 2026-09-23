<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusAiProviderSetting extends Model
{
    protected $fillable = [
        'provider',
        'settings',
        'enabled',
        'priority',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
