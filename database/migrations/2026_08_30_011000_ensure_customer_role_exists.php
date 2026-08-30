<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(['slug' => 'user'], [
            'name' => 'کاربر عادی',
            'description' => 'دسترسی استاندارد مشتری فروشگاه',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'user')->where('is_system', true)->delete();
    }
};
