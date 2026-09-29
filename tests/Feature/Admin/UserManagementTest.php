<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_paginated_searchable_user_grid(): void
    {
        $admin = $this->superAdmin();
        User::factory()->count(14)->create();
        $target = User::factory()->create(['name' => 'کاربر ویژه', 'phone' => '09123456789']);

        $this->actingAs($admin)
            ->get('/admin/users?per_page=12')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 12)
                ->where('users.total', 16)
                ->where('users.last_page', 2));

        $this->actingAs($admin)
            ->get('/admin/users?search=کاربر ویژه')
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $target->id));
    }

    public function test_super_admin_can_update_role_status_panel_access_and_direct_extras(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $supportRole = Role::query()->where('slug', 'support')->firstOrFail();
        $inherited = Permission::query()->where('slug', 'support.manage')->firstOrFail();
        $extra = Permission::query()->where('slug', 'notifications.manage')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'status' => 'active',
            'role' => 'support',
            'is_admin' => true,
            'permissions' => [$inherited->id, $extra->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
            'role' => 'support',
            'is_admin' => true,
        ]);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $supportRole->id,
        ]);
        $this->assertDatabaseMissing('permission_user', [
            'user_id' => $user->id,
            'permission_id' => $inherited->id,
        ]);
        $this->assertDatabaseHas('permission_user', [
            'user_id' => $user->id,
            'permission_id' => $extra->id,
        ]);
    }

    public function test_super_admin_can_impersonate_regular_user_and_return_safely(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $user))
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_non_super_admin_cannot_impersonate_admin_account(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $impersonate = Permission::query()->where('slug', 'users.impersonate')->firstOrFail();
        $actor->permissions()->attach($impersonate);

        $target = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $target->roles()->sync([$adminRole->id]);

        $this->actingAs($actor)
            ->post(route('admin.users.impersonate', $target))
            ->assertForbidden();

        $this->assertAuthenticatedAs($actor);
    }

    public function test_non_super_admin_cannot_grant_a_role_above_their_own_permissions(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $usersManage = Permission::query()->where('slug', 'users.manage')->firstOrFail();
        $actor->permissions()->attach($usersManage);
        $target = User::factory()->create();

        $this->actingAs($actor)->patch(route('admin.users.update', $target), [
            'status' => 'active',
            'role' => 'admin',
            'is_admin' => true,
            'permissions' => [],
        ])->assertSessionHasErrors('role');

        $this->assertSame('user', $target->fresh()->role);
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_regular_user_cannot_manage_users_and_super_admin_cannot_lock_self_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();

        $admin = $this->superAdmin();
        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'status' => 'blocked',
            'role' => 'user',
            'is_admin' => false,
            'permissions' => [],
        ])->assertSessionHasErrors('is_admin');

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertSame('super-admin', $admin->fresh()->role);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => 'super-admin',
            'status' => 'active',
        ]);
    }
}
