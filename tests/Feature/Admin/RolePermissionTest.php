<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_seeder_creates_every_role_on_the_admin_guard(): void
    {
        foreach (array_keys(RolePermissionSeeder::ROLES) as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'admin']);
        }

        $expected = collect(RolePermissionSeeder::MODULES)->sum(fn ($actions) => count($actions));
        $this->assertDatabaseCount('permissions', $expected);
    }

    public function test_admin_role_holds_every_permission(): void
    {
        $admin = $this->staff('Admin');
        $total = collect(RolePermissionSeeder::MODULES)->sum(fn ($actions) => count($actions));

        $this->assertSame($total, $admin->getAllPermissions()->count());
    }

    public function test_sales_cannot_open_staff_management(): void
    {
        $this->actingAs($this->staff('Sales'), 'admin')
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_sales_cannot_open_settings(): void
    {
        $this->actingAs($this->staff('Sales'), 'admin')
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_delivery_sees_only_its_own_menu_items(): void
    {
        $response = $this->actingAs($this->staff('Delivery'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk();

        $response->assertSee('Shipments');
        $response->assertDontSee('Staff Users');
        $response->assertDontSee('Website Settings');
    }

    public function test_admin_sees_the_administration_menu(): void
    {
        $this->actingAs($this->staff('Admin'), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Staff Users')
            ->assertSee('Roles &amp; Permissions', false)
            ->assertSee('Website Settings');
    }

    public function test_inventory_role_has_read_only_access_to_categories(): void
    {
        $inventory = $this->staff('Inventory');

        $this->assertTrue($inventory->can('categories.view'));
        $this->assertFalse($inventory->can('categories.create'));
        $this->assertTrue($inventory->can('inventory.create'));
    }
}
