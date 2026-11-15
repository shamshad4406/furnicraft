@extends('admin.layouts.app')

@section('title', 'Stock ledger')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventory</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ledger</li>
@endsection

@section('content')

<x-admin.card title="Stock ledger" subtitle="{{ $movements->total() }} movement(s)"
              icon="bi-clock-history" :flush="true">

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Product, SKU, order number, reason">
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
                <option value="">Any kind</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"
                   aria-label="From">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"
                   aria-label="To">
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.inventory.movements') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>

    <x-admin.datatable :headers="['When', 'Product', 'What happened', 'Change', 'Balance', 'Who']"
                       :rows="$movements" empty="Nothing matches this filter." empty-icon="bi-clock-history">
        @foreach ($movements as $movement)
            <tr>
                <td class="small text-muted text-nowrap">
                    {{ $movement->created_at->format('d M Y') }}
                    <div style="font-size:.72rem">{{ $movement->created_at->format('H:i') }}</div>
                </td>

                <td class="small">
                    @if ($movement->product)
                        <a href="{{ route('admin.inventory.show', $movement->product) }}" class="text-decoration-none">
                            {{ $movement->product->name }}
                        </a>
                        <div class="text-muted">{{ $movement->product->sku }}</div>
                    @else
                        <span class="text-muted">Product removed</span>
                    @endif
                    @if ($movement->variant)
                        <div class="text-muted">
                            {{ collect([$movement->variant->size, $movement->variant->color])->filter()->implode(' / ') }}
                        </div>
                    @endif
                </td>

                <td class="small">
                    <span class="badge text-bg-light border">{{ $movement->type_label }}</span>
                    @if ($movement->reference_label)
                        <div class="text-muted">{{ $movement->reference_label }}</div>
                    @endif
                    @if ($movement->reason)
                        <div class="text-muted">{{ Str::limit($movement->reason, 60) }}</div>
                    @endif
                </td>

                <td class="fw-semibold text-{{ $movement->tone }}">{{ $movement->signed_quantity }}</td>

                <td class="fw-semibold">{{ $movement->balance_after }}</td>

                <td class="small text-muted">{{ $movement->actor }}</td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
