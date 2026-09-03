<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_outbox_id')->constrained('sms_outbox')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->boolean('successful')->default(false);
            $table->boolean('retryable')->default(false);
            $table->integer('provider_status')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['sms_outbox_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_attempts');
    }
};
