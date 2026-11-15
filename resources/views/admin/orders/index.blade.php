@extends('admin.layouts.app')

@section('title', 'Orders')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Orders</li>
@endsection

@section('content')

{{-- The counts double as filters: the number that needs attention is also
     the way to get at it. --}}
<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'pending',   'label' => 'Pending',    'icon' => 'bi-hourglass-split', 'tone' => 'gold', 'query' => ['status' => 'pending']],
        ['key' => 'confirmed', 'label' => 'Confirmed',  'icon' => 'bi-check2-circle',   'tone' => 'navy', 'query' => ['status' => 'confirmed']],
        ['key' => 'processing','label' => 'Preparing',  'icon' => 'bi-tools',           'tone' => 'teal', 'query' => ['status' => 'processing']],
        ['key' => 'shipped',   'label' => 'Shipped',    'icon' => 'bi-truck',           'tone' => 'teal', 'query' => ['status' => 'shipped']],
        ['key' => 'unpaid',    'label' => 'Unpaid',     'icon' => 'bi-cash-stack',      'tone' => 'rose', 'query' => ['flag' => 'unpaid']],
        ['key' => 'uninvoiced','label' => 'No invoice', 'icon' => 'bi-receipt',         'tone' => 'navy', 'query' => ['flag' => 'uninvoiced']],
    ] as $tile)
        <div class="col-6 col-lg-2">
            <a href="{{ route('admin.orders.index', $tile['query']) }}" class="ad-card ad-card--link d-block text-decoration-none">
                <div class="ad-card__body">
                    <div class="ad-stat ad-stat--{{ $tile['tone'] }}">
                        <div class="ad-stat__icon"><i class="bi {{ $tile['icon'] }}"></i></div>
                        <div>
                            <div class="ad-stat__value">{{ $summary[$tile['key']] }}</div>
                            <div class="ad-stat__label">{{ $tile['label'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<x-admin.card title="Orders" subtitle="{{ $orders->total() }} order(s)" icon="bi-bag-check" :flush="true">
    <x-slot:actions>
        {{-- Carries the current filters, so the CSV matches the screen. --}}
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </x-slot:actions>
    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-3">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Order no, invoice, name, email, phone, pincode">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="payment_status" class="form-select form-select-sm">
                <option value="">Any payment</option>
                @foreach ($paymentStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="Placed from">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="Placed to">
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Order', 'Customer', 'Items', 'Total', 'Payment', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$orders" empty="No orders match this filter." empty-icon="bi-bag">
        @foreach ($orders as $order)
            <tr>
                <td>
                    <a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold text-decoration-none">
                        {{ $order->order_number }}
                    </a>
                    <div class="small text-muted">
                        {{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}
                    </div>
                    @if ($order->invoice_number)
                        <div class="small text-muted"><i class="bi bi-receipt"></i> {{ $order->invoice_number }}</div>
                    @endif
                </td>

                <td>
                    <div class="small fw-semibold">{{ $order->customer_name }}</div>
                    <div class="small text-muted">{{ $order->customer_phone }}</div>
                    <div class="small text-muted">
                        {{ $order->shipping_address['city'] ?? '' }} — {{ $order->shipping_pincode }}
                        @unless ($order->customer_id)
                            <span class="badge text-bg-light border ms-1">Guest</span>
                        @endunless
                    </div>
                </td>

                <td class="small">
                    {{ $order->items_count }} item(s)
                    <div class="text-muted">{{ Str::limit($order->items->pluck('name')->implode(', '), 40) }}</div>
                </td>

                <td class="fw-semibold text-nowrap">
                    {{ money($order->grand_total) }}
                    @if ($order->amount_due > 0)
                        <div class="small text-danger fw-normal">{{ money($order->amount_due) }} due</div>
                    @endif
                </td>

                <td>
                    <span class="badge text-bg-{{ $order->payment_badge }}">{{ $order->payment_label }}</span>
                    <div class="small text-muted mt-1">{{ $order->payment_method === 'cod' ? 'COD' : 'Online' }}</div>
                </td>

                <td><span class="badge text-bg-{{ $order->status_badge }}">{{ $order->status_label }}</span></td>

                <td class="text-end">
                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary"
                       title="Open order"><i class="bi bi-eye"></i></a>
                    @if ($order->hasInvoice())
                        <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-secondary" title="Tax invoice"><i class="bi bi-receipt"></i></a>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
