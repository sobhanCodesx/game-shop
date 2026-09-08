<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A deployment can be retried after MySQL commits this DDL but before
        // Laravel records the migration. In that case the table is already
        // complete and the retry should only record the migration as applied.
        if (Schema::hasTable('mobile_devices')) {
            return;
        }

        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_id')->unique();
            $table->string('push_token', 255)->unique();
            $table->string('platform', 10);
            $table->string('device_name')->nullable();
            $table->string('app_version', 30)->nullable();
            $table->boolean('push_enabled')->default(true)->index();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index(['user_id', 'push_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
