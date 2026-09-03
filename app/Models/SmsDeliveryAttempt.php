<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsDeliveryAttempt extends Model
{
    protected $fillable = ['sms_outbox_id', 'attempt', 'successful', 'retryable', 'provider_status', 'provider_message_id', 'provider_response', 'error', 'completed_at'];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'retryable' => 'boolean', 'provider_response' => 'array', 'completed_at' => 'datetime'];
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(SmsOutbox::class, 'sms_outbox_id');
    }
}
