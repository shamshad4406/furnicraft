<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_create_a_staff_account(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'Nina Sharma',
                'email' => 'nina@furnicraft.in',
                'phone' => '9876543210',
                'designation' => 'Sales Executive',
                'role' => 'Sales',
                'is_active' => 1,
                'password' => 'Welcome123',
                'password_confirmation' => 'Welcome123',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'nina@furnicraft.in')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Sales'));
        $this->assertTrue($user->is_active);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'Copy Cat',
                'email' => $this->admin->email,
                'role' => 'Sales',
                'password' => 'Welcome123',
                'password_confirmation' => 'Welcome123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_disable_and_enable_a_staff_account(): void
    {
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('Sales');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.users.toggle', $staff))
            ->assertRedirect();

        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($this->admin, 'admin')->patch(route('admin.users.toggle', $staff));
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_a_disabled_user_is_signed_out_on_the_next_request(): void
    {
        $staff = User::factory()->create(['is_active' => false]);
        $staff->assignRole('Sales');

        $this->actingAs($staff, 'admin')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_the_last_active_admin_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role' => 'Sales',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->fresh()->hasRole('Admin'));
    }

    public function test_staff_deletion_is_a_soft_delete(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('Sales');

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.users.destroy', $staff))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
    }
}
