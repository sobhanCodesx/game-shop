<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_products')) {
            Schema::create('digital_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_id')->constrained()->restrictOnDelete();
                $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
                $table->string('title');
                $table->string('slug', 180)->unique();
                $table->string('short_description', 500)->nullable();
                $table->unsignedSmallInteger('support_days')->default(7);
                $table->string('status', 20)->default('draft')->index();
                $table->boolean('featured')->default(false)->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['seller_id', 'status']);
                $table->index(['game_id', 'platform_id']);
            });
        }

        if (! Schema::hasTable('digital_offers')) {
            Schema::create('digital_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_product_id')->constrained()->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('label', 80);
                $table->unsignedBigInteger('supplier_cost')->default(0);
                $table->unsignedBigInteger('price');
                $table->unsignedInteger('stock')->default(0);
                $table->unsignedInteger('reserved_stock')->default(0);
                $table->string('status', 20)->default('active')->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['digital_product_id', 'code']);
                $table->index(['digital_product_id', 'status', 'sort_order']);
            });
        }

        if (! Schema::hasTable('digital_orders')) {
            Schema::create('digital_orders', function (Blueprint $table) {
                $table->id();
                $table->string('number', 64)->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('digital_product_id')->constrained()->restrictOnDelete();
                $table->foreignId('digital_offer_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('sale_price');
                $table->unsignedBigInteger('supplier_cost')->default(0);
                $table->string('order_status', 20)->default('new')->index();
                $table->string('payment_status', 20)->default('unpaid')->index();
                $table->string('delivery_status', 20)->default('waiting')->index();
                $table->timestamp('reservation_expires_at')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['seller_id', 'order_status']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('digital_order_messages')) {
            Schema::create('digital_order_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30)->default('message');
                $table->text('message')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->timestamp('seen_at')->nullable();
                $table->timestamps();

                $table->index(['digital_order_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('digital_deliveries')) {
            Schema::create('digital_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_order_id')->unique()->constrained()->cascadeOnDelete();
                $table->text('login')->nullable();
                $table->text('password')->nullable();
                $table->text('backup_code')->nullable();
                $table->text('instructions')->nullable();
                $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('customer_viewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_deliveries');
        Schema::dropIfExists('digital_order_messages');
        Schema::dropIfExists('digital_orders');
        Schema::dropIfExists('digital_offers');
        Schema::dropIfExists('digital_products');
    }
};
