<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialContentMedia extends Model
{
    protected $fillable = [
        'type', 'path', 'thumbnail', 'mime', 'width', 'height', 'duration', 'alt', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'social_content_id');
    }
}
