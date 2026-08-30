<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\AuthenticationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_email_verification_code(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'کاربر تست',
            'email' => 'player@example.com',
            'password' => 'player1234',
            'password_confirmation' => 'player1234',
        ]);

        $response->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_email', 'player@example.com');
        $user = User::where('email', 'player@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
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
}
