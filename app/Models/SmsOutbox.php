<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsOutbox extends Model
{
    protected $table = 'sms_outbox';

    protected $fillable = ['mobile', 'pattern', 'payload', 'status', 'attempts', 'next_attempt_at', 'sent_at', 'provider_message_id', 'last_error', 'idempotency_key', 'claimed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'next_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'claimed_at' => 'datetime'];
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(SmsDeliveryAttempt::class);
    }
}
