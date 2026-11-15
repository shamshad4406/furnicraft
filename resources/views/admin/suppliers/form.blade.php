@extends('admin.layouts.app')

@section('title', $supplier->exists ? 'Edit '.$supplier->name : 'Add a supplier')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $supplier->exists ? 'Edit' : 'Add' }}</li>
@endsection

@section('content')

<form method="POST"
      action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-admin.card icon="bi-truck-front" title="Who they are">
                <div class="row g-2">
                    <div class="col-md-8">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" required maxlength="160"
                               value="{{ old('name', $supplier->name) }}"
                               class="form-control form-control-sm @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="code" class="form-label">Code</label>
                        <input type="text" id="code" name="code" maxlength="30"
                               value="{{ old('code', $supplier->code) }}"
                               class="form-control form-control-sm text-uppercase @error('code') is-invalid @enderror">
                        <div class="form-text" style="font-size:.72rem">
                            Shown on purchase orders. Left blank, one is made from the name.
                        </div>
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="contact_person" class="form-label">Who you deal with</label>
                        <input type="text" id="contact_person" name="contact_person" maxlength="120"
                               value="{{ old('contact_person', $supplier->contact_person) }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               value="{{ old('phone', $supplier->phone) }}"
                               class="form-control form-control-sm @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="alternate_phone" class="form-label">Another number</label>
                        <input type="text" id="alternate_phone" name="alternate_phone" maxlength="20"
                               value="{{ old('alternate_phone', $supplier->alternate_phone) }}"
                               class="form-control form-control-sm @error('alternate_phone') is-invalid @enderror">
                        @error('alternate_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" maxlength="255"
                               value="{{ old('email', $supplier->email) }}"
                               class="form-control form-control-sm @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="gst_number" class="form-label">GSTIN</label>
                        <input type="text" id="gst_number" name="gst_number" maxlength="15"
                               value="{{ old('gst_number', $supplier->gst_number) }}"
                               class="form-control form-control-sm text-uppercase @error('gst_number') is-invalid @enderror">
                        @error('gst_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="pan_number" class="form-label">PAN</label>
                        <input type="text" id="pan_number" name="pan_number" maxlength="10"
                               value="{{ old('pan_number', $supplier->pan_number) }}"
                               class="form-control form-control-sm text-uppercase @error('pan_number') is-invalid @enderror">
                        @error('pan_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card icon="bi-geo-alt" title="Where they are" class="mt-3">
                <div class="row g-2">
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" id="address" name="address" maxlength="300"
                               value="{{ old('address', $supplier->address) }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                        <label for="city" class="form-label">City</label>
                        <input type="text" id="city" name="city" maxlength="120"
                               value="{{ old('city', $supplier->city) }}" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                        <label for="state" class="form-label">State</label>
                        <select id="state" name="state" class="form-select form-select-sm @error('state') is-invalid @enderror">
                            <option value="">Choose a state</option>
                            @foreach ($states as $state => $code)
                                <option value="{{ $state }}" @selected(old('state', $supplier->state) === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="pincode" class="form-label">Pincode</label>
                        <input type="text" id="pincode" name="pincode" maxlength="6" inputmode="numeric"
                               value="{{ old('pincode', $supplier->pincode) }}"
                               class="form-control form-control-sm @error('pincode') is-invalid @enderror">
                        @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card icon="bi-sliders" title="How you work together">
                <label for="payment_terms_days" class="form-label">Payment terms (days)</label>
                <input type="number" id="payment_terms_days" name="payment_terms_days" min="0" max="365"
                       value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}"
                       class="form-control form-control-sm mb-2" placeholder="30">

                <label for="lead_time_days" class="form-label">Usual lead time (days)</label>
                <input type="number" id="lead_time_days" name="lead_time_days" min="0" max="365"
                       value="{{ old('lead_time_days', $supplier->lead_time_days) }}"
                       class="form-control form-control-sm mb-2" placeholder="14">
                <div class="form-text mb-3" style="font-size:.72rem">
                    Used to suggest a date when you raise a purchase order.
                </div>

                <label for="notes" class="form-label">Notes</label>
                <textarea id="notes" name="notes" rows="4" maxlength="1000"
                          class="form-control form-control-sm mb-3">{{ old('notes', $supplier->notes) }}</textarea>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $supplier->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>

                <button class="btn btn-sm btn-primary w-100">
                    {{ $supplier->exists ? 'Save changes' : 'Add supplier' }}
                </button>
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                    Cancel
                </a>
            </x-admin.card>
        </div>
    </div>
</form>

@endsection
