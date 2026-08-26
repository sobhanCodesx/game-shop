<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('background')->nullable();
            $table->date('release_date')->nullable()->index();
            $table->string('developer')->nullable();
            $table->string('publisher')->nullable();
            $table->string('age_rating', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('manufacturer')->nullable();
            $table->string('icon')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('game_platform', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->primary(['game_id', 'platform_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('product_type', 30)->index();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reserved_stock')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('featured')->default(false)->index();
            $table->string('visibility', 20)->default('public')->index();
            $table->unsignedInteger('weight')->nullable();
            $table->boolean('trade_enabled')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['category_id', 'status']);
            $table->index(['game_id', 'status']);
        });

        Schema::create('platform_product', function (Blueprint $table) {
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['platform_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('old_price')->nullable();
            $table->unsignedBigInteger('new_price');
            $table->unsignedBigInteger('old_discount_price')->nullable();
            $table->unsignedBigInteger('new_discount_price')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('platform_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('game_platform');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('games');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
