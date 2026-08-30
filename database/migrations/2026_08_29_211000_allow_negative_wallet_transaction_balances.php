<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->bigInteger('balance_after')->change());
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->unsignedBigInteger('balance_after')->change());
    }
};
