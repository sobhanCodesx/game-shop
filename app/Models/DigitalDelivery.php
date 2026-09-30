<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalDelivery extends Model
{
    protected $fillable = [
        'digital_order_id', 'login', 'password', 'backup_code',
        'instructions', 'delivered_by', 'delivered_at', 'customer_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'login' => 'encrypted',
            'password' => 'encrypted',
            'backup_code' => 'encrypted',
            'delivered_at' => 'datetime',
            'customer_viewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DigitalOrder::class, 'digital_order_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}
