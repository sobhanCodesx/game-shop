<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->string('layout', 20)->default('carousel')->after('query_type');
            $table->json('item_ids')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropColumn(['layout', 'item_ids']);
        });
    }
};
