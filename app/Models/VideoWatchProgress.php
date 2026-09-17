<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoWatchProgress extends Model
{
    protected $table = 'video_watch_progress';

    protected $fillable = [
        'user_id',
        'social_content_id',
        'position_seconds',
        'duration_seconds',
        'completed',
        'last_watched_at',
    ];

    protected function casts(): array
    {
        return [
            'position_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'completed' => 'boolean',
            'last_watched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'social_content_id');
    }
}
