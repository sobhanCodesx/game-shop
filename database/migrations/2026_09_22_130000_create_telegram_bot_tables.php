<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->text('bot_token')->nullable();
            $table->string('admin_user_id', 32)->nullable()->index();
            $table->boolean('enabled')->default(false);
            $table->boolean('write_enabled')->default(false);
            $table->boolean('publish_enabled')->default(false);
            $table->boolean('destructive_enabled')->default(false);
            $table->boolean('media_enabled')->default(true);
            $table->string('api_base_url', 500)->default('https://api.telegram.org');
            $table->boolean('use_proxy')->default(false);
            $table->string('proxy_type', 20)->default('socks5h');
            $table->string('proxy_host', 255)->nullable();
            $table->unsignedSmallInteger('proxy_port')->default(1080);
            $table->string('proxy_username', 255)->nullable();
            $table->text('proxy_password')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('bot_id', 32)->nullable();
            $table->string('bot_username', 64)->nullable();
            $table->timestamp('webhook_registered_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamp('last_health_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('telegram_bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 32);
            $table->string('chat_id', 32);
            $table->string('state', 80);
            $table->longText('context')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'chat_id']);
        });

        Schema::create('telegram_bot_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('user_id', 32)->nullable()->index();
            $table->string('chat_id', 32)->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('action', 120)->nullable()->index();
            $table->string('resource', 40)->nullable()->index();
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->string('status', 24)->default('processing')->index();
            $table->longText('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_audits');
        Schema::dropIfExists('telegram_bot_sessions');
        Schema::dropIfExists('telegram_bot_settings');
    }
};
