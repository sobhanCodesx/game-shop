<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusAiFeedback extends Model
{
    protected $table = 'nexus_ai_feedback';

    protected $fillable = ['interaction_id', 'user_id', 'value', 'reason'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(NexusAiInteraction::class, 'interaction_id');
    }
}
