<?php

namespace Tests\Feature;

use App\Jobs\SendExpoPushNotification;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobilePushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_registration_uses_authenticated_user_and_is_idempotent(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $installationId = (string) Str::uuid();

        $payload = [
            'installation_id' => $installationId,
            'push_token' => 'native-fcm-device-token',
            'push_provider' => 'fcm',
            'platform' => 'android',
            'device_name' => 'Pixel',
            'app_version' => '1.0.0',
        ];

        $this->putJson(route('mobile.devices.store'), $payload)->assertUnauthorized();
        $this->actingAs($firstUser)->putJson(route('mobile.devices.store'), $payload)->assertOk();
        $this->actingAs($firstUser)->putJson(route('mobile.devices.store'), $payload)->assertOk();

        $this->assertDatabaseCount('mobile_devices', 1);
        $this->assertDatabaseHas('mobile_devices', ['user_id' => $firstUser->id, 'installation_id' => $installationId]);

        $payload['push_token'] = 'changed-native-fcm-device-token';
        $this->actingAs($secondUser)->putJson(route('mobile.devices.store'), $payload)->assertOk();

        $this->assertDatabaseCount('mobile_devices', 1);
        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $secondUser->id,
            'installation_id' => $installationId,
            'push_token' => $payload['push_token'],
            'push_provider' => 'fcm',
            'platform' => 'android',
        ]);
    }

    public function test_user_can_only_unregister_their_own_installation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = MobileDevice::query()->create([
            'user_id' => $owner->id,
            'installation_id' => (string) Str::uuid(),
            'push_token' => 'ExponentPushToken[owner_device]',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($other)->deleteJson(route('mobile.devices.destroy', $device->installation_id))
            ->assertOk()->assertJson(['unregistered' => false]);
        $this->assertDatabaseHas('mobile_devices', ['id' => $device->id]);

        $this->actingAs($owner)->deleteJson(route('mobile.devices.destroy', $device->installation_id))
            ->assertOk()->assertJson(['unregistered' => true]);
        $this->assertDatabaseMissing('mobile_devices', ['id' => $device->id]);
    }

    public function test_expo_rejection_disables_invalid_token(): void
    {
        config()->set('services.expo_push.enabled', true);
        Http::fake([
            '*' => Http::response(['data' => [[
                'status' => 'error',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]]]),
        ]);

        $device = MobileDevice::query()->create([
            'user_id' => User::factory()->create()->id,
            'installation_id' => (string) Str::uuid(),
            'push_token' => 'ExponentPushToken[expired_device]',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        (new SendExpoPushNotification([$device->id], [
            'title' => 'نظر جدید', 'message' => 'یک نظر جدید دارید.', 'url' => '/videos/example',
        ]))->handle();

        $this->assertFalse($device->refresh()->push_enabled);
        Http::assertSent(fn ($request) => $request[0]['data']['url'] === '/videos/example');
    }

    public function test_web_logout_unregisters_only_the_device_attached_to_that_session(): void
    {
        $user = User::factory()->create();
        $firstInstallation = (string) Str::uuid();
        $secondInstallation = (string) Str::uuid();

        MobileDevice::query()->create([
            'user_id' => $user->id,
            'installation_id' => $secondInstallation,
            'push_token' => 'ExponentPushToken[second_device]',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user)->putJson(route('mobile.devices.store'), [
            'installation_id' => $firstInstallation,
            'push_token' => 'ExponentPushToken[first_device]',
            'platform' => 'android',
        ])->assertOk();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertDatabaseMissing('mobile_devices', ['installation_id' => $firstInstallation]);
        $this->assertDatabaseHas('mobile_devices', ['installation_id' => $secondInstallation]);
    }
}
