<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_product_attribute_values')) {
            Schema::create('digital_product_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
                $table->string('value', 191);
                $table->timestamps();

                $table->unique(
                    ['digital_product_id', 'attribute_id', 'value'],
                    'digital_product_attribute_value_unique',
                );
                $table->index(
                    ['attribute_id', 'value'],
                    'digital_attribute_filter_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_product_attribute_values');
    }
};
