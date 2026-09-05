<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialContentReaction extends Model
{
    protected $fillable = ['social_content_id', 'user_id', 'type'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'social_content_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
