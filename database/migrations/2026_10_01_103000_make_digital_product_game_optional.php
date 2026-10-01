<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_products') || ! Schema::hasColumn('digital_products', 'game_id')) {
            return;
        }

        Schema::table('digital_products', function (Blueprint $table): void {
            $table->unsignedBigInteger('game_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('digital_products') || ! Schema::hasColumn('digital_products', 'game_id')) {
            return;
        }

        // Do not destroy valid unlinked products during a rollback.
        if (DB::table('digital_products')->whereNull('game_id')->exists()) {
            return;
        }

        Schema::table('digital_products', function (Blueprint $table): void {
            $table->unsignedBigInteger('game_id')->nullable(false)->change();
        });
    }
};
