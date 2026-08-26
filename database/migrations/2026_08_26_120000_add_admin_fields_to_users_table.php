<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('status', 20)->default('active')->index()->after('avatar');
            $table->string('role', 40)->default('user')->index()->after('status');
            $table->boolean('is_admin')->default(false)->index()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['phone']);
            $table->dropIndex(['status']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_admin']);
            $table->dropColumn([
                'username',
                'phone',
                'avatar',
                'status',
                'role',
                'is_admin',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
