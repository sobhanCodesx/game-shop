<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsProviderSetting extends Model
{
    protected $fillable = ['provider', 'settings', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
