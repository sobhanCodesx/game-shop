<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->bigInteger('wallet_balance')->default(0)->change());
        Schema::table('orders', fn (Blueprint $table) => $table->timestamp('cashback_reversed_at')->nullable()->after('cashback_credited_at'));
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('cashback_reversed_at'));
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('wallet_balance')->default(0)->change());
    }
};
