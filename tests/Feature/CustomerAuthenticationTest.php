<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\MobileVerificationCode;
use App\Models\User;
use App\Notifications\AuthenticationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_email_verification_code(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'first_name' => 'کاربر',
            'last_name' => 'تست',
            'email' => 'player@example.com',
            'password' => 'player1234',
            'password_confirmation' => 'player1234',
        ]);

        $response->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_email', 'player@example.com');
        $user = User::where('email', 'player@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('کاربر تست', $user->name);
        $this->assertSame('کاربر', $user->first_name);
        $this->assertSame('تست', $user->last_name);
        $this->assertDatabaseHas('email_verification_codes', ['email' => $user->email, 'purpose' => 'verify_email']);
        Notification::assertSentTo($user, AuthenticationCodeNotification::class);
    }

    public function test_customer_can_verify_email_and_is_logged_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        EmailVerificationCode::create([
            'email' => $user->email,
            'purpose' => 'verify_email',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->withSession(['verification_email' => $user->email])
            ->post(route('verification.verify'), ['code' => '123456']);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verified_customer_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'player1234', 'email_verified_at' => now()]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'player1234'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_logout_expires_all_first_party_cookies(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)
            ->withCookie('google_oauth_stale', 'old-state')
            ->withCookie('playnexus_preference', 'old-value')
            ->post(route('logout'));

        $response->assertRedirect(route('home'));
        $response->assertCookieExpired('google_oauth_stale');
        $response->assertCookieExpired('playnexus_preference');
        $this->assertGuest();
    }

    public function test_password_login_ignores_a_stale_intended_url_that_would_return_404(): void
    {
        $user = User::factory()->create([
            'phone' => '09121234567',
            'phone_verified_at' => now(),
            'password' => 'player1234',
        ]);

        $this->withSession(['url.intended' => url('/account/tickets/999999')])
            ->post(route('login.store'), [
                'identifier' => '+989121234567',
                'password' => 'player1234',
            ])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_keeps_a_safe_static_intended_destination(): void
    {
        $user = User::factory()->create([
            'phone' => '09121234567',
            'phone_verified_at' => now(),
            'password' => 'player1234',
        ]);

        $this->withSession(['url.intended' => route('checkout.show')])
            ->post(route('login.store'), [
                'identifier' => '۰۹۱۲۱۲۳۴۵۶۷',
                'password' => 'player1234',
            ])
            ->assertRedirect(route('checkout.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_from_inline_prompt_keeps_the_full_local_url(): void
    {
        $user = User::factory()->create(['password' => 'player1234', 'email_verified_at' => now()]);

        $this->post(route('login.store'), [
            'identifier' => $user->email,
            'password' => 'player1234',
            'redirect' => '/explore?type=game&page=3#results',
        ])->assertRedirect('/explore?type=game&page=3#results');

        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_can_reset_password_with_email_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('reset_email', $user->email);
        Notification::assertSentTo($user, AuthenticationCodeNotification::class);

        EmailVerificationCode::where('email', $user->email)->update(['code_hash' => Hash::make('654321')]);
        $this->withSession(['reset_email' => $user->email])->post(route('password.update'), [
            'code' => '654321',
            'password' => 'newplayer123',
            'password_confirmation' => 'newplayer123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newplayer123', $user->fresh()->password));
    }

    public function test_registration_validation_rejects_invalid_data(): void
    {
        $this->post(route('register.store'), [
            'name' => '',
            'email' => 'invalid',
            'password' => '123',
            'password_confirmation' => '456',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_customer_can_register_verify_and_login_with_normalized_mobile(): void
    {
        config()->set('services.payamak_panel', ['base_url' => 'https://rest.payamak-panel.com/api/SmartSMS', 'username' => 'user', 'api_key' => 'key', 'from' => '5000', 'timeout' => 5]);
        Http::fake(['*/Send' => Http::response(['Value' => '10', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);
        $this->post(route('register.store'), ['channel' => 'mobile', 'first_name' => 'علی', 'last_name' => 'رضایی', 'phone' => '+989121234567', 'password' => 'player1234', 'password_confirmation' => 'player1234'])
            ->assertRedirect(route('verification.notice'))->assertSessionHas('verification_phone', '09121234567');
        $user = User::where('phone', '09121234567')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertNull($user->phone_verified_at);
        MobileVerificationCode::where('phone', $user->phone)->update(['code_hash' => Hash::make('123456')]);
        $this->withSession(['verification_phone' => $user->phone])->post(route('verification.verify'), ['code' => '123456'])->assertRedirect(route('home'));
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['identifier' => '۰۹۱۲۱۲۳۴۵۶۷', 'password' => 'player1234'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_mobile_customer_receives_passwordless_login_code_and_is_verified(): void
    {
        config()->set('services.payamak_panel', ['base_url' => 'https://rest.payamak-panel.com/api/SmartSMS', 'username' => 'user', 'api_key' => 'key', 'from' => '5000', 'timeout' => 5]);
        Http::fake(['*/Send' => Http::response(['Value' => '20', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);
        $user = User::factory()->create(['phone' => '09121234567', 'phone_verified_at' => null]);

        $this->post(route('login.otp.send'), ['phone' => '+989121234567'])
            ->assertRedirect(route('login.otp.notice'))
            ->assertSessionHas('login_phone', '09121234567');

        Http::assertNothingSent();
        $this->assertDatabaseHas('sms_outbox', ['mobile' => '09121234567', 'pattern' => 'otp_passwordless_login', 'status' => 'failed']);

        MobileVerificationCode::where(['phone' => $user->phone, 'purpose' => 'passwordless_login'])
            ->update(['code_hash' => Hash::make('654321')]);

        $this->withSession(['login_phone' => $user->phone])
            ->post(route('login.otp.verify'), ['code' => '654321'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }
}
