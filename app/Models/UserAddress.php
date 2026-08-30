<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'recipient_name', 'phone', 'province', 'city', 'postal_code', 'address_line', 'plaque', 'unit', 'is_default'];
    protected function casts(): array { return ['is_default' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
