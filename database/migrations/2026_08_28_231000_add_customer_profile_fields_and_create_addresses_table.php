<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('avatar');
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 50)->default('خانه');
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('province', 80);
            $table->string('city', 80);
            $table->string('postal_code', 10)->nullable();
            $table->text('address_line');
            $table->string('plaque', 20)->nullable();
            $table->string('unit', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('birth_date'));
    }
};
