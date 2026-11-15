@extends('admin.layouts.app')

@section('title', 'Returns')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Returns</li>
@endsection

@section('content')

<x-admin.card title="Returns & claims" icon="bi-arrow-return-left"
              subtitle="{{ $returns->total() }} total · {{ $openCount }} waiting on us" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.returns.index', ['flag' => 'open']) }}"
           class="btn btn-sm {{ request('flag') === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}">
            Open ({{ $openCount }})
        </a>
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Reference, order number, customer">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
                <option value="">Any kind</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.returns.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Reference', 'Order', 'Kind', 'Reason', 'Refund', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$returns" empty="Nothing has been returned." empty-icon="bi-arrow-return-left">
        @foreach ($returns as $claim)
            <tr>
                <td>
                    <a href="{{ route('admin.returns.show', $claim) }}" class="fw-semibold text-decoration-none">
                        {{ $claim->reference }}
                    </a>
                    <div class="small text-muted">{{ $claim->requested_at->format('d M Y') }}</div>
                </td>

                <td>
                    <a href="{{ route('admin.orders.show', $claim->order) }}" class="small text-decoration-none">
                        {{ $claim->order->order_number }}
                    </a>
                    <div class="small text-muted">{{ $claim->order->customer_name }}</div>
                </td>

                <td class="small">{{ $types[$claim->type] }}</td>

                <td class="small">
                    {{ $claim->reason_label }}
                    <div class="text-muted">{{ $claim->items_count }} line(s)</div>
                </td>

                <td class="small fw-semibold">
                    {{ $claim->refund_amount > 0 ? money($claim->refund_amount) : '—' }}
                </td>

                <td>
                    <span class="badge text-bg-{{ $claim->status_badge }}">{{ $statuses[$claim->status] }}</span>
                    @if ($claim->resolver)
                        <div class="text-muted" style="font-size:.72rem">{{ $claim->resolver->name }}</div>
                    @endif
                </td>

                <td class="text-end">
                    <a href="{{ route('admin.returns.show', $claim) }}" class="btn btn-sm btn-outline-secondary">
                        {{ $claim->isOpen() ? 'Handle' : 'View' }}
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
