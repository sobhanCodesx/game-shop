<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_contents', function (Blueprint $table) {
            $table->index(['status', 'published_at', 'id'], 'social_contents_explore_feed_idx');
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'product_media_explore_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('social_contents', function (Blueprint $table) {
            $table->dropIndex('social_contents_explore_feed_idx');
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropIndex('product_media_explore_feed_idx');
        });
    }
};
