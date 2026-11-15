@extends('admin.layouts.app')

@section('title', $customer->name)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($customer->name, 40) }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card icon="bi-person-heart" title="{{ $customer->name }}"
                      subtitle="Joined {{ $customer->created_at->format('d M Y') }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $customer->is_active ? 'success' : 'danger' }}">
                    {{ $customer->is_active ? 'Can sign in' : 'Disabled' }}
                </span>
            </x-slot:actions>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ad-detail__label">Contact</div>
                    <div class="small"><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></div>
                    <div class="small"><a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a></div>
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">Signing in</div>
                    <div class="small text-muted">
                        {{ $customer->last_login_at ? 'Last seen '.$customer->last_login_at->diffForHumans() : 'Never signed in' }}
                    </div>
                    @if ($customer->last_login_ip)
                        <div class="small text-muted">{{ $customer->last_login_ip }}</div>
                    @endif
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">Enquiries</div>
                    <div class="small">{{ $stats['enquiries'] }} raised</div>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card icon="bi-bag-check" title="Orders" subtitle="{{ $stats['orders'] }} placed"
                      class="mt-3" :flush="true">
            <div class="table-responsive">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Order</th><th>Items</th><th class="text-end">Total</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold text-decoration-none">
                                        {{ $order->order_number }}
                                    </a>
                                    <div class="small text-muted">
                                        {{ ($order->placed_at ?? $order->created_at)->format('d M Y') }}
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    {{ $order->items->count() }} line(s)
                                    <div>{{ Str::limit($order->items->pluck('name')->implode(', '), 40) }}</div>
                                </td>
                                <td class="text-end fw-semibold">{{ money($order->grand_total) }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $order->status_badge }}">{{ $order->status_label }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.orders.show', $order) }}"
                                       class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted small py-3">Nothing bought yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>

        @if ($customer->addresses->isNotEmpty())
            <x-admin.card icon="bi-geo-alt" title="Saved addresses" class="mt-3">
                @foreach ($customer->addresses as $address)
                    <div class="ad-consignment">
                        <strong class="small">{{ $address->name }}</strong>
                        <span class="badge text-bg-light border">{{ \App\Models\Address::TYPES[$address->type] }}</span>
                        @if ($address->is_default_shipping)
                            <span class="badge text-bg-warning">Default</span>
                        @endif
                        <div class="small text-muted">{{ $address->single_line }}</div>
                        <div class="small text-muted">{{ $address->phone }}</div>
                    </div>
                @endforeach
            </x-admin.card>
        @endif
    </div>

    <div class="col-xl-4">
        <x-admin.card icon="bi-cash-stack" title="What they are worth">
            <div class="ad-stat ad-stat--teal mb-3">
                <div class="ad-stat__icon"><i class="bi bi-currency-rupee"></i></div>
                <div>
                    <div class="ad-stat__value">{{ money($stats['spent']) }}</div>
                    <div class="ad-stat__label">Paid, across {{ $stats['orders'] }} order(s)</div>
                </div>
            </div>

            @if ($stats['cancelled'] > 0)
                <p class="small text-muted mb-0">
                    {{ $stats['cancelled'] }} order(s) cancelled.
                </p>
            @endif
        </x-admin.card>

        @can('customers.edit')
            <x-admin.card icon="bi-shield-lock" title="Access" class="mt-3">
                <form method="POST" action="{{ route('admin.customers.toggle', $customer) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm {{ $customer->is_active ? 'btn-outline-danger' : 'btn-outline-primary' }} w-100"
                            data-confirm="{{ $customer->is_active ? 'Disable this account? They will be signed out.' : 'Let this account sign in again?' }}">
                        {{ $customer->is_active ? 'Disable this account' : 'Let them sign in again' }}
                    </button>
                </form>

                <p class="small text-muted mt-2 mb-0">
                    Their email and password are theirs — staff cannot change either. An account
                    somebody else can silently take over is not an account.
                </p>
            </x-admin.card>
        @endcan
    </div>
</div>

@endsection
