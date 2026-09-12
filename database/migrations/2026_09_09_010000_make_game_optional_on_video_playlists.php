<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_playlists') && Schema::hasColumn('video_playlists', 'game_id')) {
            Schema::table('video_playlists', function (Blueprint $table) {
                $table->dropForeign(['game_id']);
                $table->dropUnique(['game_id', 'slug']);
                $table->unsignedBigInteger('game_id')->nullable()->change();
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        // Existing independent collections cannot safely be forced back onto a game.
    }
};
