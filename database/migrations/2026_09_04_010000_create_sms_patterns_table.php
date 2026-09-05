<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('provider_id', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'otp_verify_mobile' => 'CHANGE_ME_OTP_VERIFY_MOBILE',
            'otp_passwordless_login' => 'CHANGE_ME_OTP_PASSWORDLESS_LOGIN',
            'otp_reset_password' => 'CHANGE_ME_OTP_RESET_PASSWORD',
            'order_activity' => 'CHANGE_ME_ORDER_ACTIVITY',
            'order_cashback' => 'CHANGE_ME_ORDER_CASHBACK',
            'ticket_activity' => 'CHANGE_ME_TICKET_ACTIVITY',
        ];
        DB::table('sms_patterns')->insert(array_map(fn (string $code, string $providerId) => [
            'code' => $code,
            'provider_id' => $providerId,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($defaults), array_values($defaults)));
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_patterns');
    }
};
