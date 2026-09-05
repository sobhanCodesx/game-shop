<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_contents', function (Blueprint $table) {
            $table->boolean('allow_comments')->default(true)->after('views');
        });

        Schema::create('video_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('visibility', 20)->default('public')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
        });

        Schema::create('playlist_video', function (Blueprint $table) {
            $table->foreignId('video_playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->primary(['video_playlist_id', 'social_content_id']);
            $table->index(['video_playlist_id', 'position']);
        });

        Schema::create('social_content_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);
            $table->timestamps();

            $table->unique(['social_content_id', 'user_id']);
            $table->index(['social_content_id', 'type']);
        });

        Schema::create('social_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('social_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['social_content_id', 'parent_id', 'created_at']);
        });

        Schema::create('social_comment_likes', function (Blueprint $table) {
            $table->foreignId('social_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['social_comment_id', 'user_id']);
        });

        Schema::create('game_subscriptions', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_subscriptions');
        Schema::dropIfExists('social_comment_likes');
        Schema::dropIfExists('social_comments');
        Schema::dropIfExists('social_content_reactions');
        Schema::dropIfExists('playlist_video');
        Schema::dropIfExists('video_playlists');

        Schema::table('social_contents', function (Blueprint $table) {
            $table->dropColumn('allow_comments');
        });
    }
};
