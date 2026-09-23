<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusAiKnowledge extends Model
{
    protected $table = 'nexus_ai_knowledge';

    protected $fillable = ['title', 'type', 'content', 'enabled', 'priority', 'created_by'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
