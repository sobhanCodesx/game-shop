<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('compare_price')->nullable()->after('discount_price');
            $table->unsignedBigInteger('partner_price')->nullable()->after('compare_price');
            $table->unsignedBigInteger('cost_price')->nullable()->after('partner_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['compare_price', 'partner_price', 'cost_price']);
        });
    }
};
