@extends('admin.layouts.app')

@section('title', 'Inventory')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Inventory</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'low',      'label' => 'At or below reorder level', 'icon' => 'bi-exclamation-triangle', 'tone' => 'gold', 'query' => ['flag' => 'low'],  'raw' => false],
        ['key' => 'out',      'label' => 'Out of stock',              'icon' => 'bi-x-octagon',            'tone' => 'rose', 'query' => ['flag' => 'out'],  'raw' => false],
        ['key' => 'uncosted', 'label' => 'No cost price',             'icon' => 'bi-question-circle',      'tone' => 'navy', 'query' => ['flag' => 'uncosted'], 'raw' => false],
        ['key' => 'value',    'label' => 'Stock at cost',             'icon' => 'bi-cash-stack',           'tone' => 'teal', 'query' => [], 'raw' => true],
    ] as $tile)
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.inventory.index', $tile['query']) }}"
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

<x-admin.card title="Stock on hand" subtitle="{{ $products->total() }} product(s)" icon="bi-boxes" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.inventory.movements') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i> Ledger
        </a>
        <a href="{{ route('admin.inventory.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-9">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Product name or SKU">
        </div>
        <div class="col-md-2">
            <input type="hidden" name="flag" value="{{ request('flag') }}">
            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
        </div>
        <div class="col-md-1">
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>

    <x-admin.datatable :headers="['Product', 'Category', 'In stock', 'Reorder at', 'Cost', 'Value', ['label' => 'Actions', 'end' => true]]"
                       :rows="$products" empty="No products match this filter." empty-icon="bi-boxes">
        @foreach ($products as $product)
            @php
                $stock = $product->total_stock;
                $threshold = $product->effective_low_stock_threshold;
            @endphp
            <tr>
                <td>
                    <a href="{{ route('admin.inventory.show', $product) }}" class="fw-semibold text-decoration-none">
                        {{ $product->name }}
                    </a>
                    <div class="small text-muted">{{ $product->sku }}</div>
                    @if ($product->variants->isNotEmpty())
                        <div class="small text-muted">{{ $product->variants->count() }} variant(s)</div>
                    @endif
                </td>

                <td class="small text-muted">{{ $product->category?->name ?? '—' }}</td>

                <td>
                    <span class="fw-semibold {{ $stock <= 0 ? 'text-danger' : ($stock <= $threshold ? 'text-warning' : '') }}">
                        {{ $stock }}
                    </span>
                    @if ($stock <= 0)
                        <div class="small text-danger">Out of stock</div>
                    @elseif ($stock <= $threshold)
                        <div class="small text-warning">Reorder</div>
                    @endif
                </td>

                <td class="small text-muted">
                    {{ $threshold }}
                    @if ($product->low_stock_threshold === null)
                        <div style="font-size:.7rem">shop default</div>
                    @endif
                </td>

                <td class="small">{{ $product->cost_price ? money($product->cost_price) : '—' }}</td>

                <td class="small fw-semibold">
                    {{ $product->cost_price ? money((float) $product->cost_price * $stock) : '—' }}
                </td>

                <td class="text-end">
                    <a href="{{ route('admin.inventory.show', $product) }}"
                       class="btn btn-sm btn-outline-secondary" title="Ledger and adjustments">
                        <i class="bi bi-clipboard-check"></i>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
