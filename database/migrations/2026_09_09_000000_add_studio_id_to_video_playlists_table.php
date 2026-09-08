<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_playlists') && ! Schema::hasColumn('video_playlists', 'studio_id')) {
            Schema::table('video_playlists', function (Blueprint $table) {
                $table->unsignedBigInteger('studio_id')->nullable()->after('game_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('video_playlists') && Schema::hasColumn('video_playlists', 'studio_id')) {
            Schema::table('video_playlists', function (Blueprint $table) {
                $table->dropIndex(['studio_id']);
                $table->dropColumn('studio_id');
            });
        }
    }
};
