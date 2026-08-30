<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('wallet_balance')->default(0)->after('birth_date'));

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 20);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('minimum_order')->default(0);
            $table->unsignedBigInteger('maximum_discount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->json('shipping_address');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('regular_subtotal');
            $table->unsignedBigInteger('product_discount');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('coupon_discount')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->unsignedBigInteger('wallet_used')->default(0);
            $table->unsignedBigInteger('payable_amount');
            $table->decimal('cashback_percent', 5, 2)->default(2);
            $table->unsignedBigInteger('cashback_eligible_amount')->default(0);
            $table->unsignedBigInteger('cashback_amount')->default(0);
            $table->timestamp('cashback_credited_at')->nullable();
            $table->timestamp('wallet_refunded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('variant_name')->nullable();
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('regular_unit_price');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->boolean('requires_shipping')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('description');
            $table->timestamps();
            $table->unique(['order_id', 'type']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('wallet_balance'));
    }
};
