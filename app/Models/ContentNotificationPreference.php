<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentNotificationPreference extends Model
{
    protected $fillable = ['sms_enabled', 'email_enabled', 'feed_enabled'];

    protected function casts(): array
    {
        return [
            'sms_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'feed_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
