<div class="alert alert-light border small d-flex gap-2">
    <i class="bi bi-info-circle-fill text-primary"></i>
    <div>
        Cash on delivery can be switched off entirely, or allowed only with an advance once the
        order passes a value. Per-pincode COD is set under Content &amp; Delivery → Pincodes.
    </div>
</div>

<div class="row g-3">
    <x-admin.switcher name="cod_enabled" label="Cash on delivery" col="col-md-6"
                      :checked="settings()->bool('cod_enabled', true)">
        Offer cash on delivery at checkout
    </x-admin.switcher>

    <x-admin.switcher name="guest_checkout_enabled" label="Guest checkout" col="col-md-6"
                      :checked="settings()->bool('guest_checkout_enabled', true)">
        Allow ordering without an account
    </x-admin.switcher>

    <x-admin.input name="cod_advance_limit" label="COD advance above" type="number" step="1" min="0"
                   :value="settings('cod_advance_limit')" required col="col-md-4"
                   help="Orders above this value need an advance. 0 disables the advance." />

    <x-admin.input name="cod_advance_percent" label="Advance percentage" type="number" step="0.01" min="0" max="100"
                   :value="settings('cod_advance_percent')" required col="col-md-4"
                   help="Share of the order collected online before dispatch." />

    <x-admin.input name="min_order_value" label="Minimum order value" type="number" step="1" min="0"
                   :value="settings('min_order_value')" required col="col-md-4"
                   help="0 means no minimum." />

    <x-admin.textarea name="checkout_terms_note" label="Note shown at checkout" rows="2"
                      :value="settings('checkout_terms_note')" />
</div>

@php
    $limit = (float) settings('cod_advance_limit', 0);
    $percent = (float) settings('cod_advance_percent', 0);
@endphp

@if ($limit > 0 && $percent > 0)
    <div class="alert alert-warning small mt-3 mb-0">
        <i class="bi bi-calculator me-1"></i>
        Right now a {{ money($limit * 1.5) }} cash-on-delivery order would need
        <strong>{{ money($limit * 1.5 * ($percent / 100)) }}</strong> paid online first.
    </div>
@endif
