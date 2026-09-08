<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_contents', function (Blueprint $table) {
            $table->string('feed_type', 24)->default('post')->after('type')->index();
            $table->string('feed_badge', 24)->nullable()->after('feed_type');
            $table->foreignId('related_product_id')->nullable()->after('game_id')->constrained('products')->nullOnDelete();
            $table->foreignId('related_content_id')->nullable()->after('related_product_id')->constrained('social_contents')->nullOnDelete();
            $table->boolean('notify_followers')->default(true)->after('allow_comments');
        });

        Schema::create('social_content_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_content_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('path');
            $table->string('thumbnail')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['social_content_id', 'sort_order']);
        });

        Schema::create('social_content_saves', function (Blueprint $table) {
            $table->foreignId('social_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['social_content_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_content_saves');
        Schema::dropIfExists('social_content_media');

        Schema::table('social_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_content_id');
            $table->dropConstrainedForeignId('related_product_id');
            $table->dropColumn(['feed_type', 'feed_badge', 'notify_followers']);
        });
    }
};
