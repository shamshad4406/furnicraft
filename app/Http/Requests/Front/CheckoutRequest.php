<?php

namespace App\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $address = [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'line1' => ['required', 'string', 'max:180'],
            'line2' => ['nullable', 'string', 'max:180'],
            'landmark' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            // From the list, not free text: this answer decides the GST on
            // the invoice, and a hand-typed state cannot be matched reliably.
            'state' => ['required', 'string', Rule::in(array_keys(indian_states()))],
            'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
        ];

        $rules = [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'gst_number' => ['nullable', 'string', 'regex:/^[0-9A-Z]{15}$/'],

            'saved_address_id' => ['nullable', 'integer'],
            'billing_same' => ['nullable', 'boolean'],

            'payment_method' => ['required', Rule::in(['razorpay', 'cod'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
        ];

        foreach ($address as $field => $fieldRules) {
            $rules['shipping.'.$field] = $fieldRules;
        }

        // Billing is only validated when it differs from shipping.
        if (! $this->boolean('billing_same')) {
            foreach ($address as $field => $fieldRules) {
                $rules['billing.'.$field] = $fieldRules;
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'terms.accepted' => 'Please accept the terms to place your order.',
            'shipping.pincode.regex' => 'Enter a valid 6-digit delivery pincode.',
            'billing.pincode.regex' => 'Enter a valid 6-digit billing pincode.',
            'shipping.state.in' => 'Choose the delivery state from the list.',
            'billing.state.in' => 'Choose the billing state from the list.',
            'gst_number.regex' => 'A GST number is 15 characters, for example 07AABCF1234M1Z5.',
        ];
    }

    /**
     * Readable names for every field, including the nested ones.
     *
     * Without an entry here a nested rule reports itself as "the shipping.city
     * field", which is the array key, not anything the customer typed into.
     * Both address blocks are covered field by field, so nothing can fall
     * through to a raw key.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $parts = [
            'name' => 'name',
            'phone' => 'mobile number',
            'line1' => 'address',
            'line2' => 'area / street',
            'landmark' => 'landmark',
            'city' => 'city',
            'state' => 'state',
            'pincode' => 'pincode',
        ];

        $attributes = [
            'customer_name' => 'name',
            'customer_email' => 'email address',
            'customer_phone' => 'mobile number',
            'gst_number' => 'GST number',
            'payment_method' => 'payment method',
            'notes' => 'order notes',
            'terms' => 'terms',
        ];

        foreach (['shipping' => 'delivery', 'billing' => 'billing'] as $key => $label) {
            foreach ($parts as $field => $name) {
                $attributes[$key.'.'.$field] = $label.' '.$name;
            }
        }

        return $attributes;
    }
}
