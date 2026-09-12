<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google', [
            'client_id' => 'google-client-id',
            'client_secret' => 'google-client-secret',
        ]);
        Http::preventStrayRequests();
    }

    #[DataProvider('transientFailures')]
    public function test_transient_failures_recover_without_reexchanging_a_consumed_code(string $stage, int $failure): void
    {
        $calls = ['token' => 0, 'userinfo' => 0];
        Http::fake(function ($request) use (&$calls, $stage, $failure) {
            $endpoint = str_ends_with($request->url(), '/token') ? 'token' : 'userinfo';
            $calls[$endpoint]++;
            if ($endpoint === $stage && $calls[$endpoint] < 3) {
                if ($failure === 0) {
                    throw new ConnectionException('DNS timeout', 0, new ConnectException(
                        'DNS timeout', new PsrRequest($request->method(), $request->url()), null,
                        ['errno' => 28, 'primary_ip' => '', 'request_size' => 0],
                    ));
                }

                return Http::response([], $failure);
            }

            return Http::response($endpoint === 'token' ? ['access_token' => 'access-token'] : [
                'sub' => 'recovered', 'email' => 'recovered@gmail.com', 'email_verified' => true,
            ]);
        });

        $this->withSession(['google_oauth_state' => 'state', 'google_oauth_remember' => true, 'auth.redirect' => '/shop'])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect('/shop')->assertCookie(Auth::guard()->getRecallerName());

        $this->assertAuthenticated();
        $this->assertSame($stage === 'token' ? 3 : 1, $calls['token']);
        $this->assertSame($stage === 'userinfo' ? 3 : 1, $calls['userinfo']);
        $this->assertSame(1, User::where('google_id', 'recovered')->count());
    }

    public static function transientFailures(): array
    {
        return [['token', 0], ['userinfo', 0], ['userinfo', 429], ['userinfo', 503]];
    }

    public function test_persistent_connection_failure_stops_after_three_attempts_and_clears_oauth_session(): void
    {
        $attempts = 0;
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => function () use (&$attempts) {
                $attempts++;
                throw new ConnectionException('DNS unavailable');
            },
        ]);

        $this->withSession(['google_oauth_state' => 'state', 'google_oauth_remember' => true])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'ارتباط سرور با Google موقتاً برقرار نشد. لطفاً دوباره روی ورود با Google بزنید.')
            ->assertSessionMissing('google_oauth_state')->assertSessionMissing('google_oauth_remember');

        $this->assertSame(3, $attempts);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_ambiguous_token_timeout_is_not_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Response timed out', 0, new ConnectException(
                'Response timed out', new PsrRequest('POST', 'https://oauth2.googleapis.com/token'), null,
                ['errno' => 28, 'primary_ip' => '192.0.2.1', 'request_size' => 300],
            ));
        });

        $this->withSession(['google_oauth_state' => 'state'])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertSame(1, $attempts);
        $this->assertGuest();
    }

    public function test_userinfo_unauthorized_response_is_not_retried(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([], 401),
        ]);

        $this->withSession(['google_oauth_state' => 'state'])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHas('error');

        Http::assertSentCount(2);
        $this->assertGuest();
    }

    public function test_redirect_requests_only_identity_scopes_and_remembers_full_destination(): void
    {
        $response = $this->get('http://127.0.0.1:8000/auth/google?remember=1&redirect='.urlencode('/explore?type=game&page=3'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertSame('http://127.0.0.1:8000/auth/google/callback', $query['redirect_uri']);
        $response->assertSessionHas('auth.redirect', '/explore?type=game&page=3');
        $response->assertSessionHas('google_oauth_state');
        $response->assertSessionHas('google_oauth_remember', true);
    }

    public function test_new_verified_google_user_is_created_without_password_and_returns_to_destination(): void
    {
        $this->fakeGoogleUser(['sub' => 'google-1', 'email' => 'new@gmail.com', 'email_verified' => true, 'name' => 'New Player']);

        $response = $this->withSession(['google_oauth_state' => 'secure-state', 'google_oauth_remember' => true, 'auth.redirect' => '/explore?type=game&page=3'])
            ->get(route('auth.google.callback', ['state' => 'secure-state', 'code' => 'valid-code']));

        $response->assertRedirect('/explore?type=game&page=3');
        $response->assertCookie(Auth::guard()->getRecallerName());
        $user = User::where('email', 'new@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-1', $user->google_id);
        $this->assertSame('New Player', $user->name);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_google_login_without_remember_does_not_create_a_recaller_cookie(): void
    {
        $this->fakeGoogleUser(['sub' => 'google-session', 'email' => 'session@gmail.com', 'email_verified' => true]);

        $response = $this->withSession(['google_oauth_state' => 'state', 'google_oauth_remember' => false])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']));

        $response->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertAuthenticated();
    }

    public function test_missing_google_name_falls_back_to_email_prefix(): void
    {
        $this->fakeGoogleUser(['sub' => 'google-2', 'email' => 'sobhan.kh@gmail.com', 'email_verified' => true, 'name' => null]);

        $this->withSession(['google_oauth_state' => 'state'])->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']));

        $this->assertSame('sobhan.kh', User::where('google_id', 'google-2')->value('name'));
    }

    public function test_google_picture_is_stored_only_as_the_new_users_initial_avatar(): void
    {
        Storage::fake((string) config('media.disk', 'public'));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-picture', 'email' => 'picture@gmail.com', 'email_verified' => true,
                'name' => 'Picture User', 'picture' => 'https://lh3.googleusercontent.com/avatar.png',
            ]),
            'lh3.googleusercontent.com/avatar.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->withSession(['google_oauth_state' => 'state'])->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']));

        $user = User::where('google_id', 'google-picture')->firstOrFail();
        $this->assertNotNull($user->avatar);
        Storage::disk((string) config('media.disk', 'public'))->assertExists($user->avatar);
    }

    public function test_existing_password_user_is_linked_without_duplicate_or_profile_overwrite(): void
    {
        $user = User::factory()->create([
            'name' => 'نام شخصی',
            'email' => 'PLAYER@GMAIL.COM',
            'avatar' => 'avatars/custom.webp',
            'password' => 'player1234',
        ]);
        $oldPassword = $user->password;
        $this->fakeGoogleUser(['sub' => 'google-existing', 'email' => 'player@gmail.com', 'email_verified' => true, 'name' => 'Google Name', 'picture' => 'https://lh3.googleusercontent.com/avatar.jpg']);

        $this->withSession(['google_oauth_state' => 'state'])->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))->assertRedirect(route('home'));

        $user->refresh();
        $this->assertSame(1, User::withTrashed()->whereRaw('LOWER(email) = ?', ['player@gmail.com'])->count());
        $this->assertSame('google-existing', $user->google_id);
        $this->assertSame('نام شخصی', $user->name);
        $this->assertSame('avatars/custom.webp', $user->avatar);
        $this->assertSame($oldPassword, $user->password);
        $this->assertTrue(Hash::check('player1234', $user->password));

        $this->post(route('logout'));
        $this->post(route('login.store'), ['identifier' => 'player@gmail.com', 'password' => 'player1234']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_repeat_google_login_uses_the_same_user(): void
    {
        $user = User::factory()->create(['google_id' => 'google-repeat', 'email' => 'repeat@gmail.com']);
        $this->fakeGoogleUser(['sub' => 'google-repeat', 'email' => 'repeat@gmail.com', 'email_verified' => true, 'name' => 'Changed Name']);

        $this->withSession(['google_oauth_state' => 'state'])->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::where('email', 'repeat@gmail.com')->count());
        $this->assertNotSame('Changed Name', $user->fresh()->name);
    }

    public function test_unverified_email_is_rejected(): void
    {
        $this->fakeGoogleUser(['sub' => 'google-bad', 'email' => 'bad@gmail.com', 'email_verified' => false]);

        $this->withSession(['google_oauth_state' => 'state', 'auth.redirect' => '/shop'])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect('/shop')->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'bad@gmail.com']);
    }

    public function test_invalid_google_client_configuration_has_a_clear_error(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'The provided client secret is invalid.',
            ], 401),
        ]);

        $this->withSession(['google_oauth_state' => 'state'])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'تنظیمات ورود Google روی سرور معتبر نیست؛ Client Secret صحیح نیست.');

        $this->assertGuest();
    }

    public function test_blocked_user_cannot_login_with_google(): void
    {
        User::factory()->create(['email' => 'blocked@gmail.com', 'status' => 'blocked']);
        $this->fakeGoogleUser(['sub' => 'google-blocked', 'email' => 'blocked@gmail.com', 'email_verified' => true, 'name' => 'Blocked']);

        $this->withSession(['google_oauth_state' => 'state'])
            ->get(route('auth.google.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_cancelled_or_invalid_state_callback_does_not_login(): void
    {
        $this->withSession(['auth.redirect' => '/products/example'])
            ->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect('/products/example')->assertSessionHas('error');
        $this->assertGuest();

        $this->withSession(['google_oauth_state' => 'expected'])
            ->get(route('auth.google.callback', ['state' => 'different', 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    /** @param array<string, mixed> $overrides */
    private function fakeGoogleUser(array $overrides): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => 'google-id',
                'email' => 'player@gmail.com',
                'email_verified' => true,
                'name' => 'Player',
                'picture' => null,
            ], $overrides)),
        ]);
    }
}
