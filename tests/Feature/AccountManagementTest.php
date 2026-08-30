<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_customer_dashboard(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_can_open_dashboard_and_update_profile_with_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Account/Dashboard')->has('profile')->has('addresses')->where('profileCompletion', 40));

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'گیمر حرفه‌ای', 'phone' => '09123456789', 'birth_date' => '2000-01-01',
            'avatar' => UploadedFile::fake()->image('avatar.webp'),
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('گیمر حرفه‌ای', $user->name);
        $this->assertSame('09123456789', $user->phone);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_customer_can_manage_only_their_addresses(): void
    {
        $user = User::factory()->create();
        $payload = ['title' => 'خانه', 'recipient_name' => $user->name, 'phone' => '09123456789', 'province' => 'تهران', 'city' => 'تهران', 'postal_code' => '1234567890', 'address_line' => 'خیابان آزادی', 'plaque' => '۱۲', 'unit' => '۲', 'is_default' => false];

        $this->actingAs($user)->post(route('account.addresses.store'), $payload)->assertSessionHasNoErrors();
        $address = $user->addresses()->firstOrFail();
        $this->assertTrue($address->is_default);

        $otherAddress = User::factory()->create()->addresses()->create($payload);
        $this->actingAs($user)->delete(route('account.addresses.destroy', $otherAddress))->assertNotFound();
        $this->actingAs($user)->delete(route('account.addresses.destroy', $address))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
    }

    public function test_customer_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'oldplayer123']);
        $this->actingAs($user)->put(route('account.password.update'), ['current_password' => 'wrong', 'password' => 'newplayer123', 'password_confirmation' => 'newplayer123'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put(route('account.password.update'), ['current_password' => 'oldplayer123', 'password' => 'newplayer123', 'password_confirmation' => 'newplayer123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newplayer123', $user->fresh()->password));
    }
}
