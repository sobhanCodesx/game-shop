<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileVerificationCode extends Model
{
    protected $fillable = ['phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'sent_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
