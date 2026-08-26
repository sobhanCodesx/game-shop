<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_settings', function (Blueprint $table) {
            $table->id();
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('home_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('eyebrow')->nullable();
            $table->text('description')->nullable();
            $table->string('desktop_image');
            $table->string('mobile_image')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url')->nullable();
            $table->string('secondary_button_label')->nullable();
            $table->string('secondary_button_url')->nullable();
            $table->string('text_position', 20)->default('right');
            $table->string('overlay', 20)->default('dark');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_slides');
        Schema::dropIfExists('home_settings');
    }
};
