<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_content_id')->nullable()->constrained('social_contents')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->string('source_type', 32)->default('editorial')->index();
            $table->string('source_name', 120)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->string('external_id', 190)->nullable()->index();
            $table->string('dedupe_key', 190)->unique();
            $table->unsignedTinyInteger('importance_score')->default(50)->index();
            $table->decimal('confidence', 5, 4)->default(1);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamp('effective_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status', 20)->default('candidate')->index();
            $table->timestamps();

            $table->index(['game_id', 'status', 'detected_at']);
            $table->index(['type', 'status', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
