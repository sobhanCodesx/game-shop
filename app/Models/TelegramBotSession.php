<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBotSession extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'state',
        'context',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'encrypted:array',
            'expires_at' => 'datetime',
        ];
    }
}
