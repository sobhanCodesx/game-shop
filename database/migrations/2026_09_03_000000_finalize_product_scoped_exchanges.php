<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('trade_item_title')->nullable()->after('target_product_id');
            $table->text('trade_item_description')->nullable()->after('trade_item_title');
            $table->json('trade_item_images')->nullable()->after('trade_item_description');
            $table->json('trade_item_metadata')->nullable()->after('trade_item_images');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('trade_user_id')->nullable()->after('exchange_request_id')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_product_id')->nullable()->after('trade_user_id')->constrained('products')->nullOnDelete();
            $table->unsignedBigInteger('approved_trade_value')->nullable()->after('approved_product_id');
            $table->string('trade_item_title')->nullable()->after('approved_trade_value');
            $table->text('trade_item_description')->nullable()->after('trade_item_title');
            $table->json('trade_item_images')->nullable()->after('trade_item_description');
            $table->json('trade_item_metadata')->nullable()->after('trade_item_images');
            $table->unique('exchange_request_id', 'orders_exchange_request_unique');
        });
        DB::table('tickets')->where('type', 'exchange')->whereNull('trade_item_title')->update(['trade_item_title' => 'کالای پیشنهادی کاربر']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_exchange_request_unique');
            $table->dropConstrainedForeignId('approved_product_id');
            $table->dropConstrainedForeignId('trade_user_id');
            $table->dropColumn(['approved_trade_value', 'trade_item_title', 'trade_item_description', 'trade_item_images', 'trade_item_metadata']);
        });
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn(['trade_item_title', 'trade_item_description', 'trade_item_images', 'trade_item_metadata']));
    }
};
