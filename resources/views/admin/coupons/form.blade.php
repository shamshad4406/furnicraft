@extends('admin.layouts.app')

@php $editing = $coupon->exists; @endphp

@section('title', $editing ? 'Edit Coupon' : 'Add Coupon')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.coupons.index') }}">Coupons</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $coupon->code : 'Add' }}</li>
@endsection

@section('content')

<form method="POST" action="{{ $editing ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Coupon" icon="bi-ticket-perforated">
                <div class="row g-3">
                    <x-admin.input name="code" label="Code" :value="$coupon->code" required col="col-md-5"
                                   style="text-transform:uppercase"
                                   help="Letters, numbers, hyphens and underscores. Customers may type it in any case." />

                    <x-admin.select name="type" label="Discount type" col="col-md-7" required
                                    :options="$types" :value="$coupon->type"
                                    help="Free delivery ignores the value below." />

                    <x-admin.input name="value" label="Value" type="number" step="0.01" min="0"
                                   :value="$coupon->value" col="col-md-4"
                                   help="Percent for percentage, amount for fixed." />

                    <x-admin.input name="max_discount" label="Maximum discount" type="number" step="0.01" min="0"
                                   :value="$coupon->max_discount" col="col-md-4"
                                   help="Caps a percentage discount. Blank = no cap." />

                    <x-admin.input name="min_order_value" label="Minimum order value" type="number" step="0.01" min="0"
                                   :value="$coupon->min_order_value ?? 0" required col="col-md-4" />

                    <x-admin.input name="description" label="Description" :value="$coupon->description" col="col-12"
                                   help="Internal note; not shown to customers." />
                </div>
            </x-admin.card>

            <x-admin.card title="Limits & dates" icon="bi-calendar-range" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="usage_limit" label="Total redemptions" type="number" min="1"
                                   :value="$coupon->usage_limit" col="col-md-6"
                                   help="Blank = unlimited." />

                    <x-admin.input name="usage_limit_per_customer" label="Per customer" type="number" min="1"
                                   :value="$coupon->usage_limit_per_customer" col="col-md-6"
                                   help="Blank = unlimited. Guests are counted by email." />

                    <x-admin.input name="starts_at" label="Starts" type="datetime-local" col="col-md-6"
                                   :value="$coupon->starts_at?->format('Y-m-d\TH:i')"
                                   help="Blank = active immediately." />

                    <x-admin.input name="expires_at" label="Expires" type="datetime-local" col="col-md-6"
                                   :value="$coupon->expires_at?->format('Y-m-d\TH:i')"
                                   help="Blank = never expires." />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Status" icon="bi-toggles">
                <x-admin.switcher name="is_active" col="col-12" :checked="$coupon->is_active ?? true">
                    Coupon is active
                </x-admin.switcher>

                @if ($editing)
                    <dl class="row mb-0 small mt-3">
                        <dt class="col-7 text-muted fw-normal">Times used</dt>
                        <dd class="col-5 fw-semibold text-end">{{ $coupon->used_count }}</dd>
                        <dt class="col-7 text-muted fw-normal">Created</dt>
                        <dd class="col-5 fw-semibold text-end">{{ $coupon->created_at->format('d M Y') }}</dd>
                    </dl>
                @endif
            </x-admin.card>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Coupon' : 'Create Coupon' }}
                </button>
                <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
