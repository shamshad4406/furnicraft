{{--
    Customer and delivery details, shared by "new quotation" and the edit
    panel on an existing one.

    $quotation (nullable), $enquiry (nullable), $states
--}}
@php
    $shipping = $quotation?->shipping_address ?? [];

    $value = fn (string $key, $fallback = null) => old('shipping.'.$key, $shipping[$key] ?? $fallback);
@endphp

<div class="row g-2">
    <div class="col-md-4">
        <label for="customer_name" class="form-label">Customer name <span class="text-danger">*</span></label>
        <input type="text" id="customer_name" name="customer_name" required maxlength="120"
               value="{{ old('customer_name', $quotation?->customer_name ?? $enquiry?->name) }}"
               class="form-control form-control-sm @error('customer_name') is-invalid @enderror">
        @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="customer_email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" id="customer_email" name="customer_email" required maxlength="255"
               value="{{ old('customer_email', $quotation?->customer_email ?? $enquiry?->email) }}"
               class="form-control form-control-sm @error('customer_email') is-invalid @enderror">
        @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="customer_phone" class="form-label">Phone <span class="text-danger">*</span></label>
        <input type="text" id="customer_phone" name="customer_phone" required maxlength="20"
               value="{{ old('customer_phone', $quotation?->customer_phone ?? $enquiry?->phone) }}"
               class="form-control form-control-sm @error('customer_phone') is-invalid @enderror">
        @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="gst_number" class="form-label">Their GSTIN <span class="text-muted">(optional)</span></label>
        <input type="text" id="gst_number" name="gst_number" maxlength="15"
               value="{{ old('gst_number', $quotation?->gst_number) }}"
               class="form-control form-control-sm text-uppercase @error('gst_number') is-invalid @enderror">
        @error('gst_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12"><hr class="my-1"></div>

    <div class="col-md-6">
        <label for="ship_line1" class="form-label">Address</label>
        <input type="text" id="ship_line1" name="shipping[line1]" maxlength="180"
               value="{{ $value('line1') }}" class="form-control form-control-sm">
    </div>

    <div class="col-md-6">
        <label for="ship_line2" class="form-label">Area / street</label>
        <input type="text" id="ship_line2" name="shipping[line2]" maxlength="180"
               value="{{ $value('line2') }}" class="form-control form-control-sm">
    </div>

    <div class="col-md-3">
        <label for="ship_city" class="form-label">City</label>
        <input type="text" id="ship_city" name="shipping[city]" maxlength="120"
               value="{{ $value('city', $enquiry?->city) }}" class="form-control form-control-sm">
    </div>

    <div class="col-md-3">
        <label for="ship_state" class="form-label">State</label>
        {{-- From the list: the GST split on the quotation follows it, and
             the customer is held to the figure they were shown. --}}
        <select id="ship_state" name="shipping[state]"
                class="form-select form-select-sm @error('shipping.state') is-invalid @enderror">
            <option value="">Choose a state</option>
            @foreach ($states as $state => $code)
                <option value="{{ $state }}" @selected($value('state') === $state)>{{ $state }}</option>
            @endforeach
        </select>
        @error('shipping.state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="ship_pincode" class="form-label">Pincode</label>
        <input type="text" id="ship_pincode" name="shipping[pincode]" maxlength="6" inputmode="numeric"
               value="{{ $value('pincode', $enquiry?->pincode) }}"
               class="form-control form-control-sm @error('shipping.pincode') is-invalid @enderror">
        @error('shipping.pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="ship_phone" class="form-label">Delivery phone</label>
        <input type="text" id="ship_phone" name="shipping[phone]" maxlength="20"
               value="{{ $value('phone', $enquiry?->phone) }}" class="form-control form-control-sm">
    </div>

    <input type="hidden" name="shipping[name]" value="{{ $value('name', $quotation?->customer_name ?? $enquiry?->name) }}">
</div>
