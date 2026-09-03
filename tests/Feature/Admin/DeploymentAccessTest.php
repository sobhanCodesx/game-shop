<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_regular_user_cannot_open_deployments(): void
    {
        $this->get('/admin/deployments')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/deployments')->assertForbidden();
    }

    public function test_admin_can_open_deployment_page_without_configuration(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/deployments')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Deployments/Index')->where('capabilities.import', false));
    }

    public function test_impersonated_session_cannot_access_deployments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->withSession(['impersonator_id' => $admin->id])->get('/admin/deployments')->assertForbidden();
    }
}
