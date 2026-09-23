<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->unique();
            $table->text('settings');
            $table->boolean('enabled')->default(false)->index();
            $table->unsignedSmallInteger('priority')->default(100)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_ai_provider_settings');
    }
};
