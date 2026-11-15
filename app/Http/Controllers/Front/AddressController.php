<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(): View
    {
        return view('front.account.addresses', [
            'addresses' => auth('customer')->user()->addresses()->get(),
            'types' => Address::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $customerId = (int) auth('customer')->id();
        $isFirst = ! Address::where('customer_id', $customerId)->exists();

        $address = Address::create($data + [
            'customer_id' => $customerId,
            'is_default_shipping' => $isFirst || $request->boolean('is_default_shipping'),
            'is_default_billing' => $isFirst || $request->boolean('is_default_billing'),
        ]);

        $this->enforceSingleDefault($address);

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwnership($address);

        $address->update($this->validated($request, $address) + [
            'is_default_shipping' => $request->boolean('is_default_shipping'),
            'is_default_billing' => $request->boolean('is_default_billing'),
        ]);

        $this->enforceSingleDefault($address);

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorizeOwnership($address);

        $wasDefault = $address->is_default_shipping;
        $address->delete();

        // Promote another address so checkout always has one to pre-fill.
        if ($wasDefault) {
            auth('customer')->user()->addresses()->first()?->update(['is_default_shipping' => true]);
        }

        return back()->with('success', 'Address removed.');
    }

    public function makeDefault(Address $address): RedirectResponse
    {
        $this->authorizeOwnership($address);

        $address->update(['is_default_shipping' => true, 'is_default_billing' => true]);
        $this->enforceSingleDefault($address);

        return back()->with('success', 'Default address updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Address $address = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(Address::TYPES))],
            'label' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'line1' => ['required', 'string', 'max:180'],
            'line2' => ['nullable', 'string', 'max:180'],
            'landmark' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            // From the list: a saved address decides the GST on any invoice
            // for an order sent to it.
            'state' => ['required', 'string', Rule::in(array_keys(indian_states()))],
            'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
        ], [
            'pincode.regex' => 'Enter a valid 6-digit pincode.',
            'state.in' => 'Choose the state from the list.',
        ]);
    }

    /**
     * Exactly one default of each kind per customer.
     */
    protected function enforceSingleDefault(Address $address): void
    {
        DB::transaction(function () use ($address) {
            foreach (['is_default_shipping', 'is_default_billing'] as $flag) {
                if (! $address->{$flag}) {
                    continue;
                }

                Address::where('customer_id', $address->customer_id)
                    ->whereKeyNot($address->id)
                    ->update([$flag => false]);
            }
        });
    }

    protected function authorizeOwnership(Address $address): void
    {
        abort_unless($address->customer_id === auth('customer')->id(), 404);
    }
}
