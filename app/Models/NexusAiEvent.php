<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusAiEvent extends Model
{
    protected $fillable = [
        'interaction_id',
        'user_id',
        'conversation_id',
        'visitor_hash',
        'event_type',
        'entity_type',
        'entity_id',
        'entity_slug',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(NexusAiInteraction::class, 'interaction_id');
    }
}
