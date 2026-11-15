<?php

namespace Tests\Feature\Front;

use App\Models\Address;
use App\Models\Customer;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saved addresses. Two rules matter here: an address belongs to exactly one
 * customer, and a customer has exactly one default of each kind.
 */
class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->customer = $this->makeCustomer('ravi@example.com', '9811122233', 'Ravi Kumar');
    }

    protected function makeCustomer(string $email, string $phone, string $name = 'Someone Else'): Customer
    {
        return Customer::create([
            'name' => $name, 'email' => $email, 'phone' => $phone,
            'password' => 'Secret12345', 'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'home',
            'label' => 'Home',
            'name' => 'Ravi Kumar',
            'phone' => '9811122233',
            'alternate_phone' => null,
            'line1' => '12 Green Park',
            'line2' => null,
            'landmark' => null,
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function addressFor(Customer $customer, array $overrides = []): Address
    {
        return Address::create($this->payload($overrides) + ['customer_id' => $customer->id]);
    }

    /* ---------------------------------------------------------- create */

    public function test_a_customer_can_save_an_address(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->post(route('account.addresses.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('addresses', [
            'customer_id' => $this->customer->id,
            'line1' => '12 Green Park',
            'pincode' => '110001',
        ]);
    }

    public function test_the_first_address_becomes_the_default(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->post(route('account.addresses.store'), $this->payload());

        $address = Address::firstOrFail();

        $this->assertTrue($address->is_default_shipping);
        $this->assertTrue($address->is_default_billing);
    }

    public function test_an_invalid_pincode_is_refused(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->post(route('account.addresses.store'), $this->payload(['pincode' => '0123']))
            ->assertSessionHasErrors('pincode');

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_an_unknown_address_type_is_refused(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->post(route('account.addresses.store'), $this->payload(['type' => 'warehouse']))
            ->assertSessionHasErrors('type');
    }

    public function test_a_letter_in_the_phone_number_is_refused(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->post(route('account.addresses.store'), $this->payload(['phone' => '98111abcd']))
            ->assertSessionHasErrors('phone');
    }

    /* --------------------------------------------------------- defaults */

    public function test_only_one_address_stays_the_default(): void
    {
        $first = $this->addressFor($this->customer, ['line1' => 'First']);
        $first->update(['is_default_shipping' => true, 'is_default_billing' => true]);

        $second = $this->addressFor($this->customer, ['line1' => 'Second']);

        $this->actingAs($this->customer, 'customer')
            ->patch(route('account.addresses.default', $second))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_default_shipping);
        $this->assertFalse($first->fresh()->is_default_billing);
        $this->assertTrue($second->fresh()->is_default_shipping);
        $this->assertTrue($second->fresh()->is_default_billing);
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        $first = $this->addressFor($this->customer, ['line1' => 'First']);
        $first->update(['is_default_shipping' => true, 'is_default_billing' => true]);

        $second = $this->addressFor($this->customer, ['line1' => 'Second']);

        $this->actingAs($this->customer, 'customer')
            ->delete(route('account.addresses.destroy', $first))
            ->assertRedirect();

        $this->assertSoftDeleted('addresses', ['id' => $first->id]);
        $this->assertTrue($second->fresh()->is_default_shipping);
    }

    /* ---------------------------------------------------------- update */

    public function test_a_customer_can_edit_their_address(): void
    {
        $address = $this->addressFor($this->customer);

        $this->actingAs($this->customer, 'customer')
            ->put(route('account.addresses.update', $address), $this->payload(['line1' => '44 Hauz Khas']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('44 Hauz Khas', $address->fresh()->line1);
    }

    /* -------------------------------------------------------- ownership */

    public function test_another_customers_address_cannot_be_read_or_changed(): void
    {
        $other = $this->makeCustomer('other@example.com', '9800000000');
        $theirs = $this->addressFor($other, ['line1' => 'Not yours']);

        $as = fn () => $this->actingAs($this->customer, 'customer');

        $as()->put(route('account.addresses.update', $theirs), $this->payload(['line1' => 'Hijacked']))
            ->assertNotFound();

        $as()->patch(route('account.addresses.default', $theirs))->assertNotFound();
        $as()->delete(route('account.addresses.destroy', $theirs))->assertNotFound();

        $this->assertSame('Not yours', $theirs->fresh()->line1);
    }

    public function test_the_address_list_shows_only_your_own(): void
    {
        $other = $this->makeCustomer('other@example.com', '9800000000');
        $this->addressFor($this->customer, ['line1' => '12 Green Park']);
        $this->addressFor($other, ['line1' => '99 Somewhere Else']);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.addresses'))
            ->assertOk()
            ->assertSee('12 Green Park')
            ->assertDontSee('99 Somewhere Else');
    }
}
