<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->string('alt')->nullable()->after('mobile_image');
            $table->string('link_type', 20)->default('url')->after('alt');
            $table->foreignId('product_id')->nullable()->after('link_type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['alt', 'link_type']);
        });
    }
};
