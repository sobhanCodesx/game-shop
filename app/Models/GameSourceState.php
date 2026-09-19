<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSourceState extends Model
{
    protected $fillable = [
        'game_id',
        'source',
        'scope',
        'external_id',
        'source_url',
        'confidence',
        'fingerprint',
        'state',
        'observed_at',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'state' => 'array',
            'observed_at' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
