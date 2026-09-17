<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $access = config('admin-access');
        $now = now();

        foreach ($access['roles'] as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $role['name'],
                    'description' => $role['description'] ?? null,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ($access['permissions'] as $slug => $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $allPermissionIds = DB::table('permissions')->pluck('id')->all();

        foreach ($access['roles'] as $slug => $role) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            $permissionIds = $role['permissions'] === '*'
                ? $allPermissionIds
                : DB::table('permissions')->whereIn('slug', $role['permissions'])->pluck('id')->all();

            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('permission_role')->insertOrIgnore(array_map(
                fn ($permissionId) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ],
                $permissionIds,
            ));
        }

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'email' => config('admin.seed.email'),
                'name' => 'مدیر سیستم',
                'username' => 'admin',
                'status' => 'active',
                'role' => 'super-admin',
                'is_admin' => true,
                'password' => config('admin.seed.password'),
                'email_verified_at' => now(),
            ],
        );

        $superAdminRoleId = DB::table('roles')->where('slug', 'super-admin')->value('id');

        DB::table('role_user')->updateOrInsert([
            'role_id' => $superAdminRoleId,
            'user_id' => $admin->id,
        ]);

        $this->call(ProductCatalogFoundationSeeder::class);

        if (app()->environment('local')) {
            $this->call(CatalogSeeder::class);
            $this->call(StorefrontCatalogSeeder::class);
            $this->call(HomeContentSeeder::class);
        }
    }
}
