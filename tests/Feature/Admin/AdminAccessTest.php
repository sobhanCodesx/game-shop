<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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

    public function test_admin_flag_without_effective_permission_cannot_access_panel(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'status' => 'active']);

        $this->assertFalse($user->canAccessAdminPanel());
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_super_admin_can_access_dashboard_and_resources(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'super-admin',
            'status' => 'active',
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

    public function test_every_admin_route_has_an_explicit_permission_mapping(): void
    {
        $patterns = config('admin-access.route_permissions', []);

        $unmapped = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => is_string($name) && str_starts_with($name, 'admin.'))
            ->reject(fn (string $name) => $name === 'admin.resources.index')
            ->filter(function (string $name) use ($patterns): bool {
                foreach ($patterns as $pattern => $permission) {
                    if (Str::is($pattern, $name)) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();

        $this->assertSame([], $unmapped, 'Unmapped admin routes: '.implode(', ', $unmapped));
        $this->assertSame('settings.manage', $patterns['admin.nexus-ai.*']);
        $this->assertSame('sms.manage', $patterns['admin.sms-providers.*']);
        $this->assertSame('system.deployments', $patterns['admin.android-releases.*']);
    }

    public function test_legacy_view_only_permission_does_not_escalate_to_catalog_management(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $view = Permission::create([
            'name' => 'مشاهده محصولات قدیمی',
            'slug' => 'product.view',
            'group' => 'legacy',
        ]);
        $user->permissions()->attach($view);

        $this->assertFalse($user->fresh()->hasPermission('catalog.manage'));
        $this->assertFalse($user->fresh()->canAccessAdminPanel());

        foreach (['product.create', 'product.update', 'product.delete'] as $slug) {
            $permission = Permission::create([
                'name' => $slug,
                'slug' => $slug,
                'group' => 'legacy',
            ]);
            $user->permissions()->attach($permission);
        }

        $this->assertTrue($user->fresh()->hasPermission('catalog.manage'));
        $this->assertTrue($user->fresh()->canAccessAdminPanel());
    }
}
