<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSetting extends Model
{
    protected $fillable = ['id', 'content'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
