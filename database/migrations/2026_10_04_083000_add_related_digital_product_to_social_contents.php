<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('social_contents', 'related_digital_product_id')) {
            return;
        }

        Schema::table('social_contents', function (Blueprint $table) {
            $table->foreignId('related_digital_product_id')
                ->nullable()
                ->after('related_product_id')
                ->constrained('digital_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('social_contents', 'related_digital_product_id')) {
            return;
        }

        Schema::table('social_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_digital_product_id');
        });
    }
};
