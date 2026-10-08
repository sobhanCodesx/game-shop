<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 190)->unique();
            $table->string('kind', 30)->default('story');
            $table->string('subtitle', 230)->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->boolean('contains_spoilers')->default(false);
            $table->string('seo_title', 60)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at', 'id']);
            $table->index(['game_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_stories');
    }
};
