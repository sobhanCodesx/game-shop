<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('android_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32)->unique();
            $table->unsignedBigInteger('version_code')->unique();
            $table->string('disk', 32);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('release_notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('android_releases');
    }
};
