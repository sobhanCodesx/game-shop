<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('inventory_type', 30)->default('standard');
            $table->boolean('supports_variants')->default(true);
            $table->boolean('supports_shipping')->default(false);
            $table->boolean('supports_exchange')->default(false);
            $table->boolean('supports_digital_delivery')->default(false);
            $table->boolean('supports_digital_inventory')->default(false);
            $table->boolean('requires_cover')->default(true);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('input_type', 20);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false)->index();
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_visible_on_product')->default(true);
            $table->boolean('is_usable_for_variant')->default(false)->index();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('value');
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
            $table->index(['attribute_id', 'status', 'sort_order']);
        });

        Schema::create('product_type_attributes', function (Blueprint $table) {
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['product_type_id', 'attribute_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_type_id')->nullable()->after('product_type')
                ->constrained('product_types')->nullOnDelete();
            $table->index(['product_type_id', 'status']);
        });

        Schema::table('category_attributes', function (Blueprint $table) {
            $table->foreignId('attribute_id')->nullable()->after('category_id')
                ->constrained('attributes')->nullOnDelete();
            $table->index(['category_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::table('category_attributes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attribute_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type_id', 'status']);
            $table->dropConstrainedForeignId('product_type_id');
        });

        Schema::dropIfExists('product_type_attributes');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('product_types');
    }
};
