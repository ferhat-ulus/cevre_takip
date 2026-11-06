<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_optional_fortify_flows_remain_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Features::enabled(Features::resetPasswords()));
        $this->assertFalse(Features::enabled(Features::emailVerification()));
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::enabled(Features::passkeys()));
    }

    public function test_users_can_authenticate_with_their_username()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('me.username', $user->username);
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_authenticate()
    {
        $user = User::factory()->create(['active' => false]);

        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        $key = strtolower($user->username).'|127.0.0.1';
        RateLimiter::hit($key, 600);
        RateLimiter::hit($key, 600);
        RateLimiter::hit($key, 600);
        RateLimiter::hit($key, 600);
        RateLimiter::hit($key, 600);
        RateLimiter::hit($key, 600);

        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
