<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_playlists', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('video_playlists', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
