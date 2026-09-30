<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalOrderMessage extends Model
{
    protected $fillable = [
        'digital_order_id', 'user_id', 'type', 'message',
        'attachment_path', 'attachment_name', 'seen_at',
    ];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DigitalOrder::class, 'digital_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
