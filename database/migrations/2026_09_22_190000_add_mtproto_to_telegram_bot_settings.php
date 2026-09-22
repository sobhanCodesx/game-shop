<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table): void {
            $table->boolean('mtproto_enabled')->default(false)->after('media_enabled');
            $table->unsignedBigInteger('mtproto_api_id')->nullable()->after('mtproto_enabled');
            $table->text('mtproto_api_hash')->nullable()->after('mtproto_api_id');
            $table->timestamp('mtproto_initialized_at')->nullable()->after('mtproto_api_hash');
            $table->timestamp('mtproto_last_health_at')->nullable()->after('mtproto_initialized_at');
            $table->text('mtproto_last_error')->nullable()->after('mtproto_last_health_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'mtproto_enabled',
                'mtproto_api_id',
                'mtproto_api_hash',
                'mtproto_initialized_at',
                'mtproto_last_health_at',
                'mtproto_last_error',
            ]);
        });
    }
};
