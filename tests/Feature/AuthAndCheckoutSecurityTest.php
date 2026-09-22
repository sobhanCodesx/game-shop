<?php

namespace Tests\Feature;

use App\Models\MobileVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndCheckoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_password_login_does_not_create_or_send_an_otp_for_unverified_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '09121111111',
            'phone_verified_at' => null,
            'password' => 'Secret1234',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'identifier' => $user->phone,
            'password' => 'Secret1234',
            'remember' => false,
        ])->assertRedirect('/verify-email');

        $this->assertDatabaseCount('mobile_verification_codes', 0);
        $this->assertGuest();
    }

    public function test_passwordless_login_never_turns_an_unverified_phone_into_a_verified_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '09122222222',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        $this->post('/login/otp', ['phone' => $user->phone])
            ->assertRedirect('/login/otp');

        $this->assertDatabaseCount('mobile_verification_codes', 0);
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_checkout_redirects_every_unverified_user_to_phone_verification(): void
    {
        $user = User::factory()->create([
            'phone' => '09123333333',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => ['probe' => ['quantity' => 1]]])
            ->get('/checkout')
            ->assertRedirect('/checkout/verify-phone');
    }

    public function test_changing_profile_phone_invalidates_previous_phone_verification(): void
    {
        $user = User::factory()->create([
            'name' => 'Verified User',
            'phone' => '09124444444',
            'phone_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->patch('/account/profile', [
                'name' => 'Verified User',
                'phone' => '09125555555',
                'birth_date' => null,
                'remove_avatar' => false,
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('09125555555', $user->phone);
        $this->assertNull($user->phone_verified_at);
    }
}
