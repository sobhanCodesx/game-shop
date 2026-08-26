<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category_id', 'slug']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 191)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'category_attribute_id']);
            $table->index(['category_attribute_id', 'value']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->date('release_date')->nullable()->index()->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['release_date']);
            $table->dropColumn('release_date');
        });
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('category_attributes');
    }
};
