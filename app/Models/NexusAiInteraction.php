<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NexusAiInteraction extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'visitor_hash',
        'question',
        'answer',
        'intent',
        'entities',
        'provider',
        'model',
        'latency_ms',
        'context_chars',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'entities' => 'array',
            'meta' => 'array',
            'latency_ms' => 'integer',
            'context_chars' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(NexusAiFeedback::class, 'interaction_id');
    }
}
