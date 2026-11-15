<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'DIWALI10',
            'description' => 'Festive offer',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 2000,
            'min_order_value' => 5000,
            'usage_limit' => 100,
            'usage_limit_per_customer' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => 1,
        ], $overrides);
    }

    /* ----------------------------------------------------------- create */

    public function test_an_admin_can_create_a_coupon(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload())
            ->assertRedirect(route('admin.coupons.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', [
            'code' => 'DIWALI10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => 1,
        ]);
    }

    public function test_a_lowercase_code_is_stored_uppercased(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload(['code' => 'summer25']));

        $this->assertDatabaseHas('coupons', ['code' => 'SUMMER25']);
    }

    public function test_a_duplicate_code_is_refused(): void
    {
        Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 5]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload())
            ->assertSessionHasErrors('code');
    }

    public function test_a_code_with_spaces_or_symbols_is_refused(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload(['code' => 'DIWALI 10%']))
            ->assertSessionHasErrors('code');
    }

    public function test_a_percentage_over_100_is_refused(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload(['value' => 150]))
            ->assertSessionHasErrors('value');
    }

    public function test_a_fixed_amount_over_100_is_allowed(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload([
                'code' => 'FLAT3000', 'type' => 'fixed', 'value' => 3000, 'max_discount' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['code' => 'FLAT3000', 'value' => 3000]);
    }

    public function test_a_free_delivery_coupon_needs_no_value(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload([
                'code' => 'FREEDEL', 'type' => 'free_delivery', 'value' => null, 'max_discount' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['code' => 'FREEDEL', 'type' => 'free_delivery', 'value' => 0]);
    }

    public function test_an_expiry_before_the_start_date_is_refused(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.coupons.store'), $this->payload([
                'starts_at' => now()->addDays(10)->toDateTimeString(),
                'expires_at' => now()->addDays(2)->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('expires_at');
    }

    /* ----------------------------------------------------------- update */

    public function test_an_admin_can_edit_a_coupon_and_keep_its_own_code(): void
    {
        $coupon = Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 10, 'min_order_value' => 0]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.coupons.update', $coupon), $this->payload(['value' => 15]))
            ->assertRedirect(route('admin.coupons.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('15.00', (string) $coupon->fresh()->value);
    }

    public function test_toggling_flips_the_active_flag(): void
    {
        $coupon = Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.coupons.toggle', $coupon))
            ->assertRedirect();

        $this->assertFalse($coupon->fresh()->is_active);
    }

    /* ----------------------------------------------------------- delete */

    public function test_an_unused_coupon_can_be_deleted(): void
    {
        $coupon = Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'));

        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    public function test_a_coupon_used_on_a_real_order_is_kept(): void
    {
        $coupon = Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 10]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'email' => 'ravi@example.com',
            'discount' => 500,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    /* ------------------------------------------------------ permissions */

    public function test_a_role_without_coupon_permissions_is_blocked(): void
    {
        $delivery = User::factory()->create();
        $delivery->assignRole('Delivery');

        $this->actingAs($delivery, 'admin')->get(route('admin.coupons.index'))->assertForbidden();
        $this->actingAs($delivery, 'admin')->get(route('admin.coupons.create'))->assertForbidden();
    }

    public function test_a_read_only_viewer_cannot_create_or_delete(): void
    {
        // No role: permissions granted directly, so nothing is inherited.
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'coupons.view']);

        $coupon = Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($viewer, 'admin')->get(route('admin.coupons.index'))->assertOk();
        $this->actingAs($viewer, 'admin')->get(route('admin.coupons.create'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->delete(route('admin.coupons.destroy', $coupon))->assertForbidden();
    }

    public function test_the_usage_report_renders(): void
    {
        $coupon = Coupon::create(['code' => 'DIWALI10', 'type' => 'percentage', 'value' => 10]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'email' => 'ravi@example.com',
            'discount' => 500,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.coupons.show', $coupon))
            ->assertOk()
            ->assertSee('DIWALI10')
            ->assertSee('ravi@example.com');
    }
}
