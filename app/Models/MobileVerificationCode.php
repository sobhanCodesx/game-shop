<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileVerificationCode extends Model
{
    protected $fillable = ['phone', 'purpose', 'code_hash', 'code_ciphertext', 'attempts', 'expires_at', 'sent_at', 'telegram_sent_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'telegram_sent_at' => 'datetime',
        ];
    }
}
