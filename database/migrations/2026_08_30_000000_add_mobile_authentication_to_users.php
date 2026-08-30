<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::create('mobile_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11);
            $table->string('purpose', 30);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['phone', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_verification_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone_verified_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
