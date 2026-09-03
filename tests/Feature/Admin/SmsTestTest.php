<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmsTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_sms_test_page(): void
    {
        $this->get('/admin/sms-test')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/sms-test')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/sms-test')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/SmsTest/Index')->has('patterns', 6));
    }

    public function test_admin_can_send_a_pattern_test_through_outbox(): void
    {
        config()->set('services.payamak_panel.pattern_endpoint', 'https://sms.example.test/pattern');
        config()->set('services.payamak_panel.username', 'user');
        config()->set('services.payamak_panel.api_key', 'key');
        config()->set('sms.patterns.otp_verify_mobile', '12345');
        Http::fake(['sms.example.test/*' => Http::response(['Value' => '88', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/sms-test', [
            'mobile' => '+989121234567',
            'pattern' => 'otp_verify_mobile',
            'variables' => ['code' => '654321'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('sms_outbox', ['mobile' => '09121234567', 'pattern' => 'otp_verify_mobile', 'status' => 'sent', 'provider_message_id' => '88']);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/pattern' && $request['text'] === '654321');
    }
}
