<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_ai_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('type', 32)->default('fact')->index();
            $table->text('content');
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedSmallInteger('priority')->default(100)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('nexus_ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 64)->nullable()->index();
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->string('intent', 64)->default('general')->index();
            $table->json('entities')->nullable();
            $table->string('provider', 64)->nullable()->index();
            $table->string('model', 160)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('context_chars')->default(0);
            $table->string('status', 24)->default('success')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
            $table->index(['created_at', 'intent']);
        });

        Schema::create('nexus_ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_id')->unique()->constrained('nexus_ai_interactions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->tinyInteger('value');
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('nexus_ai_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_id')->nullable()->constrained('nexus_ai_interactions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('conversation_id')->nullable()->index();
            $table->string('visitor_hash', 64)->nullable()->index();
            $table->string('event_type', 64)->index();
            $table->string('entity_type', 48)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_slug', 190)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_ai_events');
        Schema::dropIfExists('nexus_ai_feedback');
        Schema::dropIfExists('nexus_ai_interactions');
        Schema::dropIfExists('nexus_ai_knowledge');
    }
};
