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

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'user'], ['name' => 'کاربر عادی', 'is_system' => true]);
        Role::firstOrCreate(['slug' => 'support'], ['name' => 'پشتیبانی', 'is_system' => true]);
    }

    public function test_admin_sees_paginated_searchable_user_grid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(14)->create();
        $target = User::factory()->create(['name' => 'کاربر ویژه', 'phone' => '09123456789']);
        $this->actingAs($admin)->get('/admin/users?per_page=12')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index')->has('users.data', 12)->where('users.total', 16)->where('users.last_page', 2));
        $this->actingAs($admin)->get('/admin/users?search=کاربر ویژه')->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $target->id));
    }

    public function test_admin_can_update_role_status_panel_access_and_permissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'مدیریت پشتیبانی', 'slug' => 'support.manage', 'group' => 'support']);
        $this->actingAs($admin)->patch(route('admin.users.update', $user), ['status' => 'blocked', 'role' => 'support', 'is_admin' => true, 'permissions' => [$permission->id]])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'blocked', 'role' => 'support', 'is_admin' => true]);
        $this->assertDatabaseHas('permission_user', ['user_id' => $user->id, 'permission_id' => $permission->id]);
    }

    public function test_admin_can_impersonate_user_and_return_safely(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($admin)->post(route('admin.users.impersonate', $user))->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_regular_user_cannot_manage_users_and_admin_cannot_lock_self_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true, 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.users.update', $admin), ['status' => 'blocked', 'role' => 'user', 'is_admin' => false, 'permissions' => []])->assertSessionHasErrors('is_admin');
        $this->assertTrue($admin->fresh()->is_admin);
    }
}
