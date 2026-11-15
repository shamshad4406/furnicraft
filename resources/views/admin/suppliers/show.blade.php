@extends('admin.layouts.app')

@section('title', $supplier->name)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($supplier->name, 40) }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card icon="bi-truck-front" title="{{ $supplier->name }}" subtitle="Code {{ $supplier->code }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $supplier->is_active ? 'success' : 'secondary' }}">
                    {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                </span>
                @can('suppliers.edit')
                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                @endcan
            </x-slot:actions>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ad-detail__label">Contact</div>
                    <div class="small">{{ $supplier->contact_person ?: '—' }}</div>
                    @if ($supplier->phone)
                        <div class="small"><a href="tel:{{ $supplier->phone }}">{{ $supplier->phone }}</a></div>
                    @endif
                    @if ($supplier->alternate_phone)
                        <div class="small text-muted">{{ $supplier->alternate_phone }}</div>
                    @endif
                    @if ($supplier->email)
                        <div class="small"><a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a></div>
                    @endif
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">Address</div>
                    <div class="small text-muted">{{ $supplier->single_line ?: '—' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">Terms</div>
                    <div class="small">
                        @if ($supplier->payment_terms_days !== null)
                            Net {{ $supplier->payment_terms_days }} days
                        @else
                            <span class="text-muted">Not recorded</span>
                        @endif
                    </div>
                    @if ($supplier->lead_time_days !== null)
                        <div class="small text-muted">{{ $supplier->lead_time_days }} day lead time</div>
                    @endif
                    @if ($supplier->gst_number)
                        <div class="small text-muted mt-1">GSTIN {{ $supplier->gst_number }}</div>
                    @endif
                    @if ($supplier->pan_number)
                        <div class="small text-muted">PAN {{ $supplier->pan_number }}</div>
                    @endif
                </div>
            </div>

            @if ($supplier->notes)
                <div class="alert alert-light border small mt-3 mb-0">{{ $supplier->notes }}</div>
            @endif
        </x-admin.card>

        <x-admin.card icon="bi-cart-plus" title="Purchase orders"
                      subtitle="{{ $supplier->purchaseOrders->count() }} most recent" class="mt-3" :flush="true">
            <div class="table-responsive">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Number</th><th>Expected</th><th class="text-end">Value</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($supplier->purchaseOrders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.purchases.show', $order) }}" class="fw-semibold text-decoration-none">
                                        {{ $order->number }}
                                    </a>
                                    <div class="small text-muted">{{ $order->created_at->format('d M Y') }}</div>
                                </td>
                                <td class="small {{ $order->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    {{ $order->expected_on?->format('d M Y') ?? '—' }}
                                    @if ($order->isOverdue()) <div>Overdue</div> @endif
                                </td>
                                <td class="text-end fw-semibold">{{ money($order->grand_total) }}</td>
                                <td><span class="badge text-bg-{{ $order->status_badge }}">{{ $order->status_label }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('admin.purchases.show', $order) }}"
                                       class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted small py-3">Nothing ordered yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card icon="bi-cash-stack" title="Outstanding">
            <div class="ad-stat ad-stat--gold">
                <div class="ad-stat__icon"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="ad-stat__value">{{ money($supplier->outstanding_value) }}</div>
                    <div class="ad-stat__label">On orders not yet received</div>
                </div>
            </div>
        </x-admin.card>

        @can('purchases.create')
            @if ($supplier->is_active)
                <x-admin.card icon="bi-cart-plus" title="Order from them" class="mt-3">
                    <a href="{{ route('admin.purchases.create', ['supplier' => $supplier->id]) }}"
                       class="btn btn-sm btn-primary w-100">New purchase order</a>
                </x-admin.card>
            @endif
        @endcan

        @can('suppliers.edit')
            <x-admin.card icon="bi-toggle-on" title="Status" class="mt-3">
                <form method="POST" action="{{ route('admin.suppliers.toggle', $supplier) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary w-100">
                        {{ $supplier->is_active ? 'Deactivate' : 'Reactivate' }}
                    </button>
                </form>
                <p class="small text-muted mt-2 mb-0">
                    An inactive supplier stays on past purchase orders but cannot be chosen for new ones.
                </p>
            </x-admin.card>
        @endcan
    </div>
</div>

@endsection
