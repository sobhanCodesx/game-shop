<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentAsset extends Model
{
    protected $fillable = [
        'resource',
        'resource_id',
        'slot',
        'kind',
        'path',
        'original_name',
        'mime',
        'size',
        'alt',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
