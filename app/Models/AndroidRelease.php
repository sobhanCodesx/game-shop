<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AndroidRelease extends Model
{
    protected $fillable = [
        'version',
        'version_code',
        'disk',
        'file_path',
        'file_name',
        'file_size',
        'checksum_sha256',
        'release_notes',
        'is_active',
        'released_by',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'file_size' => 'integer',
            'is_active' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @return array{version: string, version_code: int}
     */
    public static function nextVersion(): array
    {
        $latest = static::query()->orderByDesc('version_code')->first(['version', 'version_code']);
        $nextCode = ((int) ($latest?->version_code ?? 0)) + 1;

        if (! $latest) {
            return ['version' => '1.0.0', 'version_code' => 1];
        }

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', (string) $latest->version, $parts) === 1) {
            return [
                'version' => $parts[1].'.'.$parts[2].'.'.(((int) $parts[3]) + 1),
                'version_code' => $nextCode,
            ];
        }

        return [
            'version' => '1.0.'.max(0, $nextCode - 1),
            'version_code' => $nextCode,
        ];
    }

    public function directUrl(): string
    {
        $baseUrl = config("filesystems.disks.{$this->disk}.url");

        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/').'/'.ltrim($this->file_path, '/');
        }

        return url('/storage/'.ltrim($this->file_path, '/'));
    }
}
