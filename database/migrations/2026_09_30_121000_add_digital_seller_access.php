<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'digital.catalog.manage' => ['مدیریت محصولات دیجیتال', 'digital'],
            'digital.orders.manage' => ['مدیریت سفارش‌های دیجیتال', 'digital'],
            'digital.delivery.manage' => ['تحویل و گفت‌وگوی سفارش دیجیتال', 'digital'],
        ];

        foreach ($permissions as $slug => [$name, $group]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => $group, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['slug' => 'digital-seller'],
            [
                'name' => 'فروشنده دیجیتال',
                'description' => 'مدیریت فقط محصولات، سفارش‌ها و تحویل‌های دیجیتال متعلق به خود فروشنده',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $roleId = DB::table('roles')->where('slug', 'digital-seller')->value('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [...array_keys($permissions), 'dashboard.view'])
            ->pluck('id')
            ->all();

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Preserve role/permission assignments on rollback to avoid access surprises.
    }
};
