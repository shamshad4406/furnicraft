@extends('admin.layouts.app')

@section('title', 'Quotations')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Quotations</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'draft',    'label' => 'Drafts',           'icon' => 'bi-pencil',        'tone' => 'navy', 'query' => ['status' => 'draft']],
        ['key' => 'sent',     'label' => 'Waiting on them',  'icon' => 'bi-hourglass',     'tone' => 'gold', 'query' => ['status' => 'sent']],
        ['key' => 'accepted', 'label' => 'Accepted, not yet ordered', 'icon' => 'bi-check2-circle', 'tone' => 'teal', 'query' => ['status' => 'accepted']],
        ['key' => 'lapsed',   'label' => 'Lapsed',           'icon' => 'bi-calendar-x',    'tone' => 'rose', 'query' => ['flag' => 'lapsed']],
    ] as $tile)
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.quotations.index', $tile['query']) }}"
               class="ad-card ad-card--link d-block text-decoration-none">
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

<x-admin.card title="Quotations" subtitle="{{ $quotations->total() }} quotation(s)"
              icon="bi-file-earmark-text" :flush="true">
    <x-slot:actions>
        @can('quotations.create')
            <a href="{{ route('admin.quotations.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New quotation
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-7">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Number, customer name, email or phone">
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.quotations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Number', 'Customer', 'Lines', 'Total', 'Valid until', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$quotations" empty="No quotations match this filter." empty-icon="bi-file-earmark-text">
        @foreach ($quotations as $quotation)
            <tr>
                <td>
                    <a href="{{ route('admin.quotations.show', $quotation) }}" class="fw-semibold text-decoration-none">
                        {{ $quotation->number }}
                    </a>
                    <div class="small text-muted">{{ $quotation->created_at->format('d M Y') }}</div>
                    @if ($quotation->enquiry)
                        <div class="small text-muted">
                            <i class="bi bi-chat-left-text"></i> {{ $quotation->enquiry->reference }}
                        </div>
                    @endif
                </td>

                <td>
                    <div class="small fw-semibold">{{ $quotation->customer_name }}</div>
                    <div class="small text-muted">{{ $quotation->customer_phone }}</div>
                </td>

                <td class="small">{{ $quotation->items_count }}</td>

                <td class="fw-semibold text-nowrap">{{ money($quotation->grand_total) }}</td>

                <td class="small">
                    @if ($quotation->valid_until)
                        {{ $quotation->valid_until->format('d M Y') }}
                        @if ($quotation->days_left !== null)
                            <div class="{{ $quotation->days_left <= 3 ? 'text-danger' : 'text-muted' }}">
                                {{ $quotation->days_left }} day(s) left
                            </div>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>

                <td>
                    <span class="badge text-bg-{{ $quotation->status_badge }}">{{ $quotation->status_label }}</span>
                    @if ($quotation->order)
                        <div class="small text-muted">{{ $quotation->order->order_number }}</div>
                    @endif
                </td>

                <td class="text-end">
                    <a href="{{ route('admin.quotations.show', $quotation) }}"
                       class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    @if ($quotation->status !== 'draft')
                        <a href="{{ route('admin.quotations.print', $quotation) }}" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-secondary" title="Print"><i class="bi bi-printer"></i></a>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
