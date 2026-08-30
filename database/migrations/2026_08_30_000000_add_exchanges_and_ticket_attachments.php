<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('type', 30)->default('support')->after('subject')->index();
            $table->string('exchange_status', 30)->nullable()->after('status')->index();
            $table->unsignedBigInteger('exchange_offer_amount')->nullable();
            $table->timestamp('exchange_offer_responded_at')->nullable();
            $table->timestamp('exchange_completed_at')->nullable();
            $table->timestamp('exchange_credited_at')->nullable();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_reply_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('type', 10);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('ticket_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->index(['ticket_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'type']);
            $table->dropConstrainedForeignId('ticket_id');
        });
        Schema::dropIfExists('ticket_attachments');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['exchange_status']);
            $table->dropColumn(['type', 'exchange_status', 'exchange_offer_amount', 'exchange_offer_responded_at', 'exchange_completed_at', 'exchange_credited_at']);
        });
    }
};
