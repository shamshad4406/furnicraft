@extends('front.layouts.app')

@section('title', 'Checkout')

@php
    $default = $addresses->firstWhere('is_default_shipping', true) ?? $addresses->first();
    $old = fn ($key, $fallback = null) => old($key, $fallback);

    /**
     * What a delivery field starts out holding.
     *
     * Order matters: whatever was just typed wins, then the saved address, so
     * the block stays internally consistent — a saved address must not supply
     * its street while the pincode comes from a check done for somewhere else.
     * The pincode from the cart's delivery check is the fallback after that,
     * and name and phone fall back to the account itself.
     */
    $shippingValue = function (string $field) use ($old, $default, $customer, $pincode) {
        $fallback = $default?->{$field} ?? match ($field) {
            'pincode' => $pincode,
            'name' => $customer?->name,
            'phone' => $customer?->phone,
            default => null,
        };

        return $old('shipping.'.$field, $fallback);
    };
@endphp

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Checkout</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('cart.index') }}">Cart</a></li>
                <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <form method="POST" action="{{ route('checkout.store') }}" id="checkoutForm" novalidate>
            @csrf

            <div class="row g-4">
                <div class="col-lg-8">
                    @guest('customer')
                        <div class="alert alert-light border d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle"></i>
                            <div class="small">
                                Checking out as a guest.
                                <a href="{{ route('customer.login') }}">Sign in</a> to use a saved address and track the order.
                            </div>
                        </div>
                    @endguest

                    {{-- --------------------------------------------- contact --}}
                    <div class="fc-panel mb-3">
                        <h2 class="fc-panel__title">Your details</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label">Full name <span class="text-danger">*</span></label>
                                <input type="text" id="customer_name" name="customer_name"
                                       value="{{ $old('customer_name', $customer?->name) }}"
                                       class="form-control @error('customer_name') is-invalid @enderror" required>
                                @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="customer_phone" class="form-label">Mobile <span class="text-danger">*</span></label>
                                <input type="tel" id="customer_phone" name="customer_phone"
                                       value="{{ $old('customer_phone', $customer?->phone) }}"
                                       class="form-control @error('customer_phone') is-invalid @enderror" required>
                                @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="customer_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="customer_email" name="customer_email"
                                       value="{{ $old('customer_email', $customer?->email) }}"
                                       class="form-control @error('customer_email') is-invalid @enderror" required>
                                @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">The order confirmation and invoice go here.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="gst_number" class="form-label">GST number <span class="text-muted small">(optional)</span></label>
                                <input type="text" id="gst_number" name="gst_number" value="{{ $old('gst_number') }}"
                                       class="form-control @error('gst_number') is-invalid @enderror"
                                       placeholder="07AABCF1234M1Z5" maxlength="15">
                                @error('gst_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">For a GST invoice in your company's name.</div>
                            </div>
                        </div>
                    </div>

                    {{-- -------------------------------------------- shipping --}}
                    <div class="fc-panel mb-3">
                        <h2 class="fc-panel__title">Delivery address</h2>

                        @if ($addresses->isNotEmpty())
                            <div class="fc-address-picker mb-3">
                                @foreach ($addresses as $address)
                                    <label class="fc-address-option">
                                        <input type="radio" name="saved_address_id" value="{{ $address->id }}"
                                               data-address='@json($address->toSnapshot())'
                                               @checked($default && $default->id === $address->id)>
                                        <span>
                                            <strong>{{ $address->name }}</strong>
                                            <small>{{ $address->single_line }}</small>
                                            <small>{{ $address->phone }}</small>
                                        </span>
                                    </label>
                                @endforeach
                                <label class="fc-address-option">
                                    <input type="radio" name="saved_address_id" value="" data-address-new>
                                    <span><strong>Use a new address</strong></span>
                                </label>
                            </div>
                        @endif

                        <div class="row g-3" id="shippingFields">
                            @foreach ([
                                ['name', 'Full name', 'col-md-6', true],
                                ['phone', 'Mobile', 'col-md-6', true],
                                ['line1', 'Flat / house / building', 'col-12', true],
                                ['line2', 'Area / street', 'col-12', false],
                                ['landmark', 'Landmark', 'col-md-6', false],
                                ['pincode', 'Pincode', 'col-md-6', true],
                                ['city', 'City', 'col-md-6', true],
                                ['state', 'State', 'col-md-6', true],
                            ] as [$field, $label, $col, $required])
                                <div class="{{ $col }}">
                                    <label for="shipping_{{ $field }}" class="form-label">
                                        {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
                                    </label>

                                    @if ($field === 'state')
                                        {{-- A list, not free text: the GST on the invoice is decided
                                             by this answer, and "UP" typed by hand is how an
                                             inter-state sale gets taxed as if it were local. --}}
                                        @include('front.partials.state-select', [
                                            'id' => 'shipping_state',
                                            'name' => 'shipping[state]',
                                            'selected' => $shippingValue('state'),
                                            'invalid' => $errors->has('shipping.state'),
                                            'required' => true,
                                        ])
                                    @else
                                        <input type="text" id="shipping_{{ $field }}" name="shipping[{{ $field }}]"
                                               value="{{ $shippingValue($field) }}"
                                               class="form-control @error('shipping.'.$field) is-invalid @enderror"
                                               @if ($required) required @endif
                                               @if ($field === 'pincode') maxlength="6" inputmode="numeric" @endif>
                                    @endif

                                    @error('shipping.'.$field) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                        </div>

                        @auth('customer')
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="save_address" value="1"
                                       id="save_address" @checked(old('save_address', true))>
                                <label class="form-check-label small" for="save_address">Save this address for next time</label>
                            </div>
                        @endauth
                    </div>

                    {{-- --------------------------------------------- billing --}}
                    <div class="fc-panel mb-3">
                        <h2 class="fc-panel__title">Billing address</h2>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="billing_same" value="1"
                                   id="billing_same" @checked(old('billing_same', true)) data-billing-toggle>
                            <label class="form-check-label" for="billing_same">Same as the delivery address</label>
                        </div>

                        <div class="row g-3 {{ old('billing_same', true) ? 'd-none' : '' }}" id="billingFields">
                            @foreach ([
                                ['name', 'Full name', 'col-md-6'],
                                ['phone', 'Mobile', 'col-md-6'],
                                ['line1', 'Flat / house / building', 'col-12'],
                                ['line2', 'Area / street', 'col-12'],
                                ['landmark', 'Landmark', 'col-md-6'],
                                ['pincode', 'Pincode', 'col-md-6'],
                                ['city', 'City', 'col-md-6'],
                                ['state', 'State', 'col-md-6'],
                            ] as [$field, $label, $col])
                                <div class="{{ $col }}">
                                    <label for="billing_{{ $field }}" class="form-label">{{ $label }}</label>

                                    @if ($field === 'state')
                                        @include('front.partials.state-select', [
                                            'id' => 'billing_state',
                                            'name' => 'billing[state]',
                                            'selected' => $old('billing.state'),
                                            'invalid' => $errors->has('billing.state'),
                                            'required' => false,
                                        ])
                                    @else
                                        <input type="text" id="billing_{{ $field }}" name="billing[{{ $field }}]"
                                               value="{{ $old('billing.'.$field) }}"
                                               class="form-control @error('billing.'.$field) is-invalid @enderror"
                                               @if ($field === 'pincode') maxlength="6" inputmode="numeric" @endif>
                                    @endif

                                    @error('billing.'.$field) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- --------------------------------------------- payment --}}
                    <div class="fc-panel">
                        <h2 class="fc-panel__title">Payment method</h2>

                        <div class="fc-payment-options">
                            <label class="fc-payment-option {{ $razorpayReady ? '' : 'is-disabled' }}">
                                <input type="radio" name="payment_method" value="razorpay"
                                       @checked(old('payment_method', 'razorpay') === 'razorpay')
                                       @disabled(! $razorpayReady) required>
                                <span>
                                    <strong><i class="bi bi-credit-card me-1"></i> Pay online</strong>
                                    <small>Card, UPI, net banking or wallet — secured by Razorpay.</small>
                                    @unless ($razorpayReady)
                                        <small class="text-danger">Temporarily unavailable.</small>
                                    @endunless
                                </span>
                            </label>

                            @if ($codAllowed)
                                <label class="fc-payment-option">
                                    <input type="radio" name="payment_method" value="cod"
                                           @checked(old('payment_method') === 'cod')>
                                    <span>
                                        <strong><i class="bi bi-cash-coin me-1"></i> Cash on delivery</strong>
                                        @if ($codAdvance > 0)
                                            <small>Pay {{ money($codAdvance) }} now, the rest on delivery.</small>
                                        @else
                                            <small>Pay the full amount when your order arrives.</small>
                                        @endif
                                    </span>
                                </label>
                            @endif
                        </div>

                        @error('payment_method') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                        <div class="mt-3">
                            <label for="notes" class="form-label">Delivery notes <span class="text-muted small">(optional)</span></label>
                            <textarea id="notes" name="notes" rows="2" maxlength="500"
                                      class="form-control @error('notes') is-invalid @enderror"
                                      placeholder="Lift available, call before arriving, etc.">{{ $old('notes') }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- ---------------------------------------------- summary --}}
                <div class="col-lg-4">
                    <div class="fc-checkout-items mb-3">
                        @foreach ($totals->lines as $line)
                            <div class="fc-checkout-item">
                                <img src="{{ $line['product']->thumbnail_url ?? asset('images/logo.svg') }}" alt="" loading="lazy">
                                <div>
                                    <span>{{ $line['name'] }}</span>
                                    <small>
                                        {{ $line['variant_label'] ? $line['variant_label'].' · ' : '' }}Qty {{ $line['quantity'] }}
                                    </small>
                                </div>
                                <strong>{{ money($line['line_total']) }}</strong>
                            </div>
                        @endforeach
                    </div>

                    @include('front.partials.order-summary')

                    <div class="form-check mt-3">
                        <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox"
                               name="terms" value="1" id="terms" @checked(old('terms'))>
                        <label class="form-check-label small" for="terms">
                            I accept the <a href="{{ route('page', 'terms-conditions') }}" target="_blank">terms</a>
                            and <a href="{{ route('page', 'return-policy') }}" target="_blank">return policy</a>.
                        </label>
                        @error('terms') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    @if (settings('checkout_terms_note'))
                        <p class="small text-muted mt-2">{{ settings('checkout_terms_note') }}</p>
                    @endif

                    <button type="submit" class="btn btn-gold btn-lg w-100 mt-3" data-submit-once>
                        Place Order <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

@endsection
