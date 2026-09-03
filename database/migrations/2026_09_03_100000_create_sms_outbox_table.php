<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 11);
            $table->string('pattern', 64);
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_outbox');
    }
};
