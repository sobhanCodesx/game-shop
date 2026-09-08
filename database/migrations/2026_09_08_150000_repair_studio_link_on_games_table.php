<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('games') || Schema::hasColumn('games', 'studio_id')) {
            return;
        }

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('studio_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        // This is a repair migration. The owning studios migration removes the
        // column, so rolling this migration back must not damage a valid schema.
    }
};
