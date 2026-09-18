<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedicated_admin_login_routes_do_not_exist(): void
    {
        $this->get('/admin/login')->assertNotFound();
        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_guest_is_redirected_from_admin_dashboard_to_main_login(): void
    {
        $this->get('/admin')->assertRedirect('/login?redirect=%2Fadmin');
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

    public function test_admin_uses_the_main_login_and_logout_flow(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
            'role' => 'super-admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'identifier' => $admin->email,
            'password' => 'password',
            'redirect' => '/admin',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
