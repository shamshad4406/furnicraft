@extends('admin.layouts.app')

@section('title', 'Coupon '.$coupon->code)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.coupons.index') }}">Coupons</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $coupon->code }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-lg-4">
        <x-admin.card :title="$coupon->code" :subtitle="$coupon->description" icon="bi-ticket-perforated">
            <x-slot:actions>
                @can('coupons.edit')
                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                @endcan
            </x-slot:actions>

            <dl class="row mb-0 small">
                <dt class="col-6 text-muted fw-normal">Discount</dt>
                <dd class="col-6 fw-semibold text-end">{{ $coupon->summary }}</dd>

                <dt class="col-6 text-muted fw-normal">Minimum order</dt>
                <dd class="col-6 fw-semibold text-end">
                    {{ $coupon->min_order_value > 0 ? money($coupon->min_order_value) : 'None' }}
                </dd>

                <dt class="col-6 text-muted fw-normal">Times used</dt>
                <dd class="col-6 fw-semibold text-end">
                    {{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}
                </dd>

                <dt class="col-6 text-muted fw-normal">Per customer</dt>
                <dd class="col-6 fw-semibold text-end">{{ $coupon->usage_limit_per_customer ?? 'Unlimited' }}</dd>

                <dt class="col-6 text-muted fw-normal">Starts</dt>
                <dd class="col-6 fw-semibold text-end">{{ $coupon->starts_at?->format('d M Y') ?? 'Any time' }}</dd>

                <dt class="col-6 text-muted fw-normal">Expires</dt>
                <dd class="col-6 fw-semibold text-end mb-0">{{ $coupon->expires_at?->format('d M Y') ?? 'Never' }}</dd>
            </dl>
        </x-admin.card>

        <x-admin.card title="Total discount given" icon="bi-cash-stack" class="mt-3">
            <div class="ad-stat ad-stat--gold">
                <div class="ad-stat__icon"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="ad-stat__value">{{ money($coupon->usages()->sum('discount')) }}</div>
                    <div class="ad-stat__label">across {{ $coupon->usages()->count() }} order(s)</div>
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-8">
        <x-admin.card title="Redemptions" icon="bi-clock-history" :flush="true">
            <x-admin.datatable :headers="['When', 'Order', 'Customer', 'Discount']"
                               :rows="$usages" empty="This coupon has not been used yet." empty-icon="bi-ticket-perforated">
                @foreach ($usages as $usage)
                    <tr>
                        <td class="text-muted text-nowrap" style="font-size:.8rem">
                            {{ $usage->created_at->format('d M Y, H:i') }}
                        </td>
                        <td>
                            @if ($usage->order)
                                <code>{{ $usage->order->order_number }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted" style="font-size:.82rem">
                            {{ $usage->email ?? 'Customer #'.$usage->customer_id }}
                        </td>
                        <td class="fw-semibold">{{ money($usage->discount) }}</td>
                    </tr>
                @endforeach
            </x-admin.datatable>
        </x-admin.card>
    </div>
</div>

@endsection
