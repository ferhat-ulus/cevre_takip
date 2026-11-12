<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\ModuleUserAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
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

        $response->assertOk()
            ->assertJsonPath('me.username', $user->username)
            ->assertJsonPath('redirect', '/moduller');
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_can_view_the_module_selection_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ModulSecimi')
                ->where('modules.0.name', 'Çevre Takip')
                ->where('modules.0.available', true)
                ->where('modules.0.href', '/moduller/cevre-takip')
                ->has('modules', 1)
            );
    }

    public function test_guests_are_redirected_to_login_from_module_selection(): void
    {
        $this->get(route('modules.index'))
            ->assertRedirect(route('login'));
    }

    public function test_environment_pages_require_explicit_module_selection(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['active_module' => null])
            ->get(route('dashboard'))
            ->assertRedirect(route('modules.index'));
    }

    public function test_environment_api_requires_explicit_module_selection(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['active_module' => null])
            ->getJson('/api/visits')
            ->assertForbidden();
    }

    public function test_selecting_environment_module_unlocks_its_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('modules.select.environment'))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_user_without_environment_access_cannot_see_or_select_the_module(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ModuleUserAccess::query()->where('user_id', $user->id)->delete();

        $this->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('modules', 0));

        $this->post(route('modules.select.environment'))
            ->assertRedirect(route('modules.index'));
    }

    public function test_viewer_can_open_environment_module_but_cannot_change_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ModuleUserAccess::query()->where('user_id', $user->id)->update(['role' => 'viewer']);

        $this->get(route('dashboard'))->assertOk();

        $this->postJson('/api/firms', ['name' => 'Görüntüleme Yetkili Tesis'])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Bu modülde veri değiştirme yetkiniz bulunmuyor.']);
    }

    public function test_system_admin_can_open_user_access_center_without_selecting_a_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['active_module' => null])
            ->get(route('platform.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Kullanicilar'));

        $this->actingAs(User::factory()->create())
            ->get(route('platform.users.index'))
            ->assertForbidden();
    }

    public function test_returning_to_module_selection_requires_selecting_a_module_again(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('modules.index'))
            ->assertOk();

        $this->get(route('dashboard'))
            ->assertRedirect(route('modules.index'));
    }

    public function test_login_clears_a_previous_module_selection(): void
    {
        $user = User::factory()->create();

        $this->withSession(['active_module' => 'environment'])
            ->postJson('/api/login', [
                'username' => $user->username,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('redirect', '/moduller');

        $this->get(route('dashboard'))->assertRedirect(route('modules.index'));
    }

    public function test_user_with_a_temporary_password_must_change_it_before_using_modules(): void
    {
        $user = User::factory()->create(['must_change' => true]);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk();
    }

    public function test_forced_password_change_updates_the_session_version_and_unlocks_modules(): void
    {
        $user = User::factory()->create(['must_change' => true, 'sess_ver' => 4]);

        $this->actingAs($user)
            ->postJson(route('password.change.store'), [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk()
            ->assertJsonPath('redirect', '/moduller');

        $this->assertFalse($user->fresh()->must_change);
        $this->assertSame(5, $user->fresh()->sess_ver);
        $this->get(route('modules.index'))->assertOk();
    }

    public function test_stale_or_disabled_user_sessions_are_ended(): void
    {
        $user = User::factory()->create(['sess_ver' => 2]);

        $this->actingAs($user)
            ->withSession(['auth_session_version' => 1])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $inactiveUser = User::factory()->create(['active' => false]);
        $this->actingAs($inactiveUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
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
