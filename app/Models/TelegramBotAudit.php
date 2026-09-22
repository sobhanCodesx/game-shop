<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBotAudit extends Model
{
    protected $fillable = [
        'update_id',
        'user_id',
        'chat_id',
        'message_id',
        'action',
        'resource',
        'resource_id',
        'status',
        'payload',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'update_id' => 'integer',
            'message_id' => 'integer',
            'resource_id' => 'integer',
        ];
    }
}
