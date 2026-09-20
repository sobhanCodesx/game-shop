<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('android_app_pages', function (Blueprint $table) {
            $table->id();
            $table->json('content')->nullable();
            $table->timestamps();
        });

        // The production deployment runs migrations after switching files.
        // Reset an aggressive FPM OPcache here so the following request loads
        // the newly deployed route files instead of an older in-memory copy.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('android_app_pages');
    }
};
