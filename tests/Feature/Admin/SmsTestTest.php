<?php

namespace Tests\Feature\Admin;

use App\Models\SmsPatternConfiguration;
use App\Models\User;
use App\Services\Sms\SmsPattern;
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
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SmsTest/Index')
            ->has('patterns', 7)
            ->where('patterns.0.value', 'plain_test')
            ->where('patterns.0.configured', true));
    }

    public function test_admin_can_manage_pattern_ids_without_environment_changes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $patterns = collect(SmsPattern::cases())->map(fn ($pattern) => [
            'code' => $pattern->value,
            'provider_id' => $pattern->value === 'otp_verify_mobile' ? 'body-12345' : null,
            'is_active' => true,
        ])->all();

        $this->actingAs($admin)->get('/admin/sms-patterns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/SmsPatterns/Index')->has('patterns', 6));

        $this->actingAs($admin)->put('/admin/sms-patterns', [
            'patterns' => $patterns,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('sms_patterns', [
            'code' => 'otp_verify_mobile',
            'provider_id' => 'body-12345',
            'is_active' => true,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_sync_command_fills_defaults_without_overwriting_real_ids(): void
    {
        SmsPatternConfiguration::query()->where('code', 'otp_verify_mobile')->update(['provider_id' => 'real-body-id']);
        SmsPatternConfiguration::query()->where('code', 'ticket_activity')->update(['provider_id' => null]);

        $this->artisan('sms:sync-patterns')->assertSuccessful();

        $this->assertDatabaseHas('sms_patterns', ['code' => 'otp_verify_mobile', 'provider_id' => 'real-body-id']);
        $this->assertDatabaseHas('sms_patterns', ['code' => 'ticket_activity', 'provider_id' => 'CHANGE_ME_TICKET_ACTIVITY']);
    }

    public function test_admin_can_send_a_pattern_test_through_outbox(): void
    {
        config()->set('services.payamak_panel.pattern_endpoint', 'https://sms.example.test/pattern');
        config()->set('services.payamak_panel.username', 'user');
        config()->set('services.payamak_panel.api_key', 'key');
        SmsPatternConfiguration::query()->where('code', 'otp_verify_mobile')->update(['provider_id' => '12345']);
        Http::fake(['sms.example.test/*' => Http::response(['Value' => '88', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/sms-test', [
            'mobile' => '+989121234567',
            'pattern' => 'otp_verify_mobile',
            'variables' => ['code' => '654321'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('sms_outbox', ['mobile' => '09121234567', 'pattern' => 'otp_verify_mobile', 'status' => 'sent', 'provider_message_id' => '88']);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/pattern' && $request['text'] === "654321\nلغو11");
    }

    public function test_admin_can_send_a_service_pattern_with_its_default_template(): void
    {
        config()->set('services.payamak_panel.base_url', 'https://sms.example.test/api');
        config()->set('services.payamak_panel.username', 'user');
        config()->set('services.payamak_panel.api_key', 'key');
        config()->set('services.payamak_panel.from', '5000');
        Http::fake(['sms.example.test/api/Send' => Http::response(['Value' => '98', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/sms-test', [
            'mobile' => '09121234567',
            'pattern' => 'otp_verify_mobile',
            'variables' => ['code' => '654321'],
        ])->assertRedirect()->assertSessionHas('success')->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('sms_outbox', [
            'pattern' => 'otp_verify_mobile',
            'status' => 'sent',
            'provider_message_id' => '98',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/api/Send'
            && $request['text'] === "کد تأیید موبایل شما: 654321\nلغو11");
    }

    public function test_admin_can_send_plain_test_without_a_pattern_id(): void
    {
        config()->set('services.payamak_panel.base_url', 'https://sms.example.test/api');
        config()->set('services.payamak_panel.username', 'user');
        config()->set('services.payamak_panel.api_key', 'key');
        config()->set('services.payamak_panel.from', '5000');
        Http::fake(['sms.example.test/api/Send' => Http::response(['Value' => '99', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/sms-test', [
            'mobile' => '09121234567',
            'pattern' => 'plain_test',
            'variables' => ['message' => 'Test connection'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('sms_outbox', [
            'mobile' => '09121234567',
            'pattern' => 'plain_test',
            'status' => 'sent',
            'provider_message_id' => '99',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/api/Send'
            && $request['text'] === "Test connection\nلغو11"
            && $request['to'] === '09121234567');
        $this->actingAs($admin)->get('/admin/sms-test')->assertInertia(fn (Assert $page) => $page
            ->where('messages.data.0.message', "Test connection\nلغو11")
            ->where('messages.data.0.attempts.0.provider_response.RetStatus', 1)
            ->where('messages.data.0.attempts.0.provider_response.Value', '99'));
    }
}
