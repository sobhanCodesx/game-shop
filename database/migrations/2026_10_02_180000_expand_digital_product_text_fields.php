<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_products', function (Blueprint $table): void {
            $table->text('title')->change();
            $table->longText('short_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('digital_products', function (Blueprint $table): void {
            $table->string('title')->change();
            $table->text('short_description')->nullable()->change();
        });
    }
};
