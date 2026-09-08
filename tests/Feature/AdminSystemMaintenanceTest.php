<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_system_maintenance_page(): void
    {
        $this->get(route('admin.system-maintenance.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.system-maintenance.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.system-maintenance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SystemMaintenance/Index')
                ->has('environment')
                ->has('phpVersion')
                ->has('laravelVersion'));
    }

    public function test_admin_can_run_a_whitelisted_command_and_see_its_output(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Application cache cleared.');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.system-maintenance.run'), [
                'action' => 'clear-cache',
                'confirmed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('maintenance_result', fn (array $result) => $result['successful']
                && $result['commands'][0]['exit_code'] === 0
                && $result['commands'][0]['output'] === 'Application cache cleared.');
    }

    public function test_unknown_commands_and_unconfirmed_requests_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.system-maintenance.run'), ['action' => 'route:clear', 'confirmed' => true])
            ->assertSessionHasErrors('action');
        $this->actingAs($admin)
            ->post(route('admin.system-maintenance.run'), ['action' => 'migrate', 'confirmed' => false])
            ->assertSessionHasErrors('confirmed');
    }
}
