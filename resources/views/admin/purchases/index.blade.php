@extends('admin.layouts.app')

@section('title', 'Purchase orders')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Purchases</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'draft',   'label' => 'Drafts',            'icon' => 'bi-pencil',     'tone' => 'navy', 'query' => ['status' => 'draft'], 'raw' => false],
        ['key' => 'ordered', 'label' => 'Awaiting delivery', 'icon' => 'bi-hourglass',  'tone' => 'gold', 'query' => ['flag' => 'open'],    'raw' => false],
        ['key' => 'overdue', 'label' => 'Overdue',           'icon' => 'bi-calendar-x', 'tone' => 'rose', 'query' => ['flag' => 'overdue'], 'raw' => false],
        ['key' => 'value',   'label' => 'On order',          'icon' => 'bi-cash-stack', 'tone' => 'teal', 'query' => ['flag' => 'open'],    'raw' => true],
    ] as $tile)
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.purchases.index', $tile['query']) }}"
               class="ad-card ad-card--link d-block text-decoration-none">
                <div class="ad-card__body">
                    <div class="ad-stat ad-stat--{{ $tile['tone'] }}">
                        <div class="ad-stat__icon"><i class="bi {{ $tile['icon'] }}"></i></div>
                        <div>
                            <div class="ad-stat__value">
                                {{ $tile['raw'] ? money($summary[$tile['key']]) : $summary[$tile['key']] }}
                            </div>
                            <div class="ad-stat__label">{{ $tile['label'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<x-admin.card title="Purchase orders" subtitle="{{ $orders->total() }} order(s)" icon="bi-cart-plus" :flush="true">
    <x-slot:actions>
        @can('purchases.create')
            <a href="{{ route('admin.purchases.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New purchase order
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="PO number, supplier, their invoice number">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <select name="supplier" class="form-select form-select-sm">
                <option value="">Any supplier</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.purchases.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Number', 'Supplier', 'Lines', 'Expected', 'Value', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$orders" empty="No purchase orders match this filter." empty-icon="bi-cart-plus">
        @foreach ($orders as $order)
            <tr>
                <td>
                    <a href="{{ route('admin.purchases.show', $order) }}" class="fw-semibold text-decoration-none">
                        {{ $order->number }}
                    </a>
                    <div class="small text-muted">{{ $order->created_at->format('d M Y') }}</div>
                    @if ($order->supplier_invoice_number)
                        <div class="small text-muted">Inv {{ $order->supplier_invoice_number }}</div>
                    @endif
                </td>

                <td class="small">
                    <a href="{{ route('admin.suppliers.show', $order->supplier) }}" class="text-decoration-none">
                        {{ $order->supplier->name }}
                    </a>
                    <div class="text-muted"><code>{{ $order->supplier->code }}</code></div>
                </td>

                <td class="small">{{ $order->items_count }}</td>

                <td class="small {{ $order->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">
                    {{ $order->expected_on?->format('d M Y') ?? '—' }}
                    @if ($order->isOverdue()) <div>Overdue</div> @endif
                </td>

                <td class="fw-semibold text-nowrap">{{ money($order->grand_total) }}</td>

                <td><span class="badge text-bg-{{ $order->status_badge }}">{{ $order->status_label }}</span></td>

                <td class="text-end">
                    <a href="{{ route('admin.purchases.show', $order) }}" class="btn btn-sm btn-outline-secondary">
                        {{ $order->isReceivable() ? 'Receive' : 'View' }}
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
