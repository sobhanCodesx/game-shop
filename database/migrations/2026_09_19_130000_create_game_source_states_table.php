<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_source_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('source', 60);
            $table->string('scope', 40)->default('catalog');
            $table->string('external_id', 255)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->decimal('confidence', 5, 4)->default(1);
            $table->char('fingerprint', 64);
            $table->json('state');
            $table->timestamp('observed_at')->index();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['game_id', 'source', 'scope']);
            $table->index(['source', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_source_states');
    }
};
