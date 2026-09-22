<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramBotSetting extends Model
{
    protected $fillable = [
        'bot_token',
        'admin_user_id',
        'enabled',
        'write_enabled',
        'publish_enabled',
        'destructive_enabled',
        'media_enabled',
        'mtproto_enabled',
        'mtproto_api_id',
        'mtproto_api_hash',
        'mtproto_initialized_at',
        'mtproto_last_health_at',
        'mtproto_last_error',
        'transport_mode',
        'api_base_url',
        'relay_base_url',
        'relay_key',
        'use_proxy',
        'proxy_type',
        'proxy_host',
        'proxy_port',
        'proxy_username',
        'proxy_password',
        'webhook_secret',
        'bot_id',
        'bot_username',
        'webhook_registered_at',
        'last_webhook_at',
        'last_health_at',
        'last_error',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'relay_key' => 'encrypted',
            'proxy_password' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'mtproto_api_hash' => 'encrypted',
            'enabled' => 'boolean',
            'write_enabled' => 'boolean',
            'publish_enabled' => 'boolean',
            'destructive_enabled' => 'boolean',
            'media_enabled' => 'boolean',
            'mtproto_enabled' => 'boolean',
            'mtproto_api_id' => 'integer',
            'use_proxy' => 'boolean',
            'proxy_port' => 'integer',
            'webhook_registered_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'last_health_at' => 'datetime',
            'mtproto_initialized_at' => 'datetime',
            'mtproto_last_health_at' => 'datetime',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
