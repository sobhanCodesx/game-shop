<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Load the file directly so this migration also works when an old
        // production config cache is still present during deployment.
        $access = require config_path('admin-access.php');
        $now = now();

        foreach ($access['roles'] as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $role['name'],
                    'description' => $role['description'] ?? null,
                    'is_system' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        foreach ($access['permissions'] as $slug => $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $allPermissionIds = DB::table('permissions')->pluck('id')->all();

        foreach ($access['roles'] as $slug => $role) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = $role['permissions'] === '*'
                ? $allPermissionIds
                : DB::table('permissions')->whereIn('slug', $role['permissions'])->pluck('id')->all();

            DB::table('permission_role')->where('role_id', $roleId)->delete();

            if ($permissionIds !== []) {
                DB::table('permission_role')->insertOrIgnore(array_map(
                    fn ($permissionId) => [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ],
                    $permissionIds,
                ));
            }
        }

        $roleIds = DB::table('roles')->pluck('id', 'slug');

        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($roleIds): void {
                foreach ($users as $user) {
                    $roleId = $roleIds[$user->role] ?? null;
                    if (! $roleId) {
                        continue;
                    }

                    DB::table('role_user')->updateOrInsert([
                        'role_id' => $roleId,
                        'user_id' => $user->id,
                    ]);
                }
            });

        // A super-admin account must always be recognized as an admin account.
        DB::table('users')->where('role', 'super-admin')->update(['is_admin' => true]);
    }

    public function down(): void
    {
        // Access-control data is intentionally preserved on rollback to avoid
        // accidentally reopening protected admin routes or dropping assignments.
    }
};
