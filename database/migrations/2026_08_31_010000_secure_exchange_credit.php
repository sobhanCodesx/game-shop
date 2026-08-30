<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('target_product_id')->nullable()->after('product_id')->constrained('products')->nullOnDelete();
            $table->foreignId('exchange_order_id')->nullable()->after('exchange_offer_amount')->constrained('orders')->nullOnDelete();
            $table->unsignedBigInteger('exchange_credit_applied')->default(0)->after('exchange_order_id');
            $table->timestamp('exchange_credit_expires_at')->nullable()->after('exchange_credit_applied');
            $table->timestamp('exchange_received_at')->nullable();
            $table->timestamp('exchange_cancelled_at')->nullable();
            $table->timestamp('exchange_expired_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('exchange_request_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->unsignedBigInteger('exchange_credit_used')->default(0);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->unsignedBigInteger('exchange_credit_used')->default(0));

        DB::table('tickets')->where('type', 'exchange')->whereNull('target_product_id')->update([
            'target_product_id' => DB::raw('product_id'),
        ]);
        DB::table('tickets')->where('type', 'exchange')->whereIn('exchange_status', ['offered', 'accepted'])->whereNull('exchange_credit_expires_at')->update([
            'exchange_credit_expires_at' => now()->addDays(30),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('exchange_credit_used'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_request_id');
            $table->dropColumn('exchange_credit_used');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_order_id');
            $table->dropConstrainedForeignId('target_product_id');
            $table->dropColumn(['exchange_credit_applied', 'exchange_credit_expires_at', 'exchange_received_at', 'exchange_cancelled_at', 'exchange_expired_at']);
        });
    }
};
