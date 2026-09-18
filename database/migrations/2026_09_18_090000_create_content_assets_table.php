<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_assets', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 24);
            $table->unsignedBigInteger('resource_id');
            $table->string('slot', 32)->default('attachment');
            $table->string('kind', 16);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['resource', 'resource_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_assets');
    }
};
