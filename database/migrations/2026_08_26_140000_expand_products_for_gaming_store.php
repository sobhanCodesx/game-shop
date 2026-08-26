<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('internal_code')->nullable()->unique()->after('sku');
            $table->unsignedBigInteger('compare_price')->nullable()->after('discount_price');
            $table->unsignedBigInteger('partner_price')->nullable()->after('compare_price');
            $table->unsignedBigInteger('cost_price')->nullable()->after('partner_price');
            $table->unsignedInteger('sold_stock')->default(0)->after('reserved_stock');
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('sold_stock');
            $table->string('availability', 30)->default('in_stock')->index()->after('low_stock_threshold');
            $table->string('condition', 20)->nullable()->after('weight');
            $table->unsignedInteger('length')->nullable()->after('condition');
            $table->unsignedInteger('width')->nullable()->after('length');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->string('barcode')->nullable()->index()->after('height');
            $table->boolean('requires_shipping')->default(true)->after('barcode');
            $table->string('shipping_class')->nullable()->after('requires_shipping');
            $table->string('delivery_method', 30)->nullable()->after('shipping_class');
            $table->text('purchase_notes')->nullable()->after('delivery_method');
            $table->text('delivery_notes')->nullable()->after('purchase_notes');
            $table->text('return_policy')->nullable()->after('delivery_notes');
            $table->string('warranty')->nullable()->after('return_policy');
            $table->json('tags')->nullable()->after('warranty');
            $table->unsignedInteger('minimum_quantity')->default(1)->after('tags');
            $table->unsignedInteger('maximum_quantity')->nullable()->after('minimum_quantity');
            $table->string('badge')->nullable()->after('maximum_quantity');
            $table->boolean('allow_reviews')->default(true)->after('trade_enabled');
            $table->boolean('allow_comments')->default(true)->after('allow_reviews');
            $table->boolean('allow_questions')->default(true)->after('allow_comments');
            $table->boolean('show_stock')->default(true)->after('allow_questions');
            $table->string('seo_keywords')->nullable()->after('seo_description');
            $table->timestamp('published_at')->nullable()->index()->after('canonical');
            $table->timestamp('expires_at')->nullable()->index()->after('published_at');

            $table->index(['product_type', 'status']);
            $table->index(['price', 'status']);
            $table->index(['stock', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type', 'status']);
            $table->dropIndex(['price', 'status']);
            $table->dropIndex(['stock', 'status']);
            $table->dropIndex(['availability']);
            $table->dropIndex(['barcode']);
            $table->dropIndex(['published_at']);
            $table->dropIndex(['expires_at']);
            $table->dropUnique(['internal_code']);
            $table->dropColumn([
                'internal_code', 'compare_price', 'partner_price', 'cost_price', 'sold_stock',
                'low_stock_threshold', 'availability', 'condition', 'length', 'width', 'height',
                'barcode', 'requires_shipping', 'shipping_class', 'delivery_method', 'purchase_notes',
                'delivery_notes', 'return_policy', 'warranty', 'tags', 'minimum_quantity',
                'maximum_quantity', 'badge', 'allow_reviews', 'allow_comments', 'allow_questions',
                'show_stock', 'seo_keywords', 'published_at', 'expires_at',
            ]);
        });
    }
};
