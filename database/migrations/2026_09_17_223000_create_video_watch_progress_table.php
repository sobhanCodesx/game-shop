<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_watch_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_content_id')->constrained('social_contents')->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('last_watched_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'social_content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_watch_progress');
    }
};
