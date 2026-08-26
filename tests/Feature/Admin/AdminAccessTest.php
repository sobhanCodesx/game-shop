<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_admin_login(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Auth/Login'));
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_dashboard_and_resources(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'super-admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('stats', 4));

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Resource/Index')
                ->where('resource', 'products'));
    }

    public function test_admin_can_login_and_logout(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
