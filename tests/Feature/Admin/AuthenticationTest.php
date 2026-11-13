<?php

namespace Tests\Feature\Admin;

use App\Models\LoginHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use App\Notifications\ResetPasswordNotification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);
    }

    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('Admin');

        return $user;
    }

    public function test_login_screen_is_reachable(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_staff_can_sign_in_with_valid_credentials(): void
    {
        $user = $this->admin(['password' => 'Secret123']);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user, 'admin');
        $this->assertDatabaseHas('login_histories', [
            'guard' => 'admin',
            'email' => $user->email,
            'successful' => true,
        ]);
    }

    public function test_sign_in_fails_with_a_wrong_password(): void
    {
        $user = $this->admin(['password' => 'Secret123']);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'WrongPassword1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertDatabaseHas('login_histories', [
            'email' => $user->email,
            'successful' => false,
        ]);
    }

    public function test_a_disabled_account_cannot_sign_in(): void
    {
        $user = $this->admin(['password' => 'Secret123', 'is_active' => false]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertDatabaseHas('login_histories', [
            'email' => $user->email,
            'reason' => 'Account disabled',
        ]);
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $user = $this->admin(['password' => 'Secret123']);
        $throttle = (int) settings('admin_login_throttle', 5);

        for ($i = 0; $i < $throttle; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email' => $user->email,
                'password' => 'Nope12345',
            ]);
        }

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertTrue(
            LoginHistory::where('reason', 'Rate limited')->exists(),
            'The rate-limited attempt should be recorded in the login history.'
        );
    }

    public function test_staff_can_sign_out(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_password_reset_link_is_emailed_and_points_at_the_admin_panel(): void
    {
        Notification::fake();
        $user = $this->admin();

        $this->post(route('admin.password.email'), ['email' => $user->email])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            return $notification->guard === 'admin'
                && str_contains($mail->actionUrl, '/admin/reset-password/');
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = $this->admin(['password' => 'Secret123']);
        $token = Password::broker('users')->createToken($user);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertRedirect(route('admin.login'));

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'BrandNew123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user->fresh(), 'admin');
    }

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
