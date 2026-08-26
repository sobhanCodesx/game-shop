<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('content_type', 20);
            $table->string('query_type', 20)->default('latest');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('items_limit')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('social_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->boolean('featured')->default(false)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_contents');
        Schema::dropIfExists('home_sections');
    }
};
