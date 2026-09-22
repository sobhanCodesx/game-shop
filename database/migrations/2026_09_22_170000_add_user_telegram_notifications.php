<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_user_id', 32)->nullable()->unique()->after('phone_verified_at');
            $table->string('telegram_chat_id', 32)->nullable()->unique()->after('telegram_user_id');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_chat_id');
        });

        Schema::table('content_notification_preferences', function (Blueprint $table): void {
            $table->boolean('telegram_enabled')->default(false)->after('feed_enabled');
        });

        Schema::table('mobile_verification_codes', function (Blueprint $table): void {
            $table->text('code_ciphertext')->nullable()->after('code_hash');
            $table->timestamp('telegram_sent_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_verification_codes', function (Blueprint $table): void {
            $table->dropColumn(['code_ciphertext', 'telegram_sent_at']);
        });

        Schema::table('content_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('telegram_enabled');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_user_id']);
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn(['telegram_user_id', 'telegram_chat_id', 'telegram_linked_at']);
        });
    }
};
