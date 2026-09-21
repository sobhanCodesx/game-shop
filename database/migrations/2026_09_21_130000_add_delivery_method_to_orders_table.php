<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 20)->default('courier')->after('shipping_address')->index();
            $table->text('pickup_address')->nullable()->after('delivery_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_method']);
            $table->dropColumn(['delivery_method', 'pickup_address']);
        });
    }
};
