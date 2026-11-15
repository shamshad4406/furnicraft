@extends('admin.layouts.app')

@section('title', 'Reports')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Reports</li>
@endsection

@section('content')

<x-admin.card title="Sales" icon="bi-graph-up" :flush="true" class="mb-3">
    <x-slot:actions>
        <a href="{{ route('admin.reports.gst', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
            GST summary
        </a>
        <a href="{{ route('admin.reports.export', request()->query() + ['report' => 'sales']) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> CSV
        </a>
    </x-slot:actions>

    @include('admin.reports._period', ['action' => route('admin.reports.index')])

    <div class="ad-card__body">
        <div class="row g-2">
            @foreach ([
                ['label' => 'Received',    'value' => money($summary['received']),   'sub' => 'Money actually in',           'icon' => 'bi-cash-stack',  'tone' => 'teal'],
                ['label' => 'Invoiced',    'value' => money($summary['invoiced']),   'sub' => $summary['orders'].' order(s)','icon' => 'bi-receipt',     'tone' => 'navy'],
                ['label' => 'Outstanding', 'value' => money($summary['outstanding']),'sub' => 'Still to collect',            'icon' => 'bi-hourglass',   'tone' => 'rose'],
                ['label' => 'Average order','value' => money($summary['average']),   'sub' => $summary['units'].' unit(s)',  'icon' => 'bi-bar-chart',   'tone' => 'gold'],
            ] as $stat)
                <div class="col-6 col-lg-3">
                    <div class="ad-card">
                        <div class="ad-card__body">
                            <div class="ad-stat ad-stat--{{ $stat['tone'] }}">
                                <div class="ad-stat__icon"><i class="bi {{ $stat['icon'] }}"></i></div>
                                <div>
                                    <div class="ad-stat__value">{{ $stat['value'] }}</div>
                                    <div class="ad-stat__label">{{ $stat['label'] }}</div>
                                    <div class="text-muted" style="font-size:.74rem">{{ $stat['sub'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($summary['cancelled'] > 0 || $summary['returned'] > 0)
            <p class="small text-muted mt-3 mb-0">
                Not counted above: {{ $summary['cancelled'] }} cancelled and
                {{ $summary['returned'] }} returned order(s) in this period.
            </p>
        @endif
    </div>
</x-admin.card>

{{-- ------------------------------------------------------------ by day --}}
<x-admin.card title="Day by day" icon="bi-calendar3" :flush="true" class="mb-3">
    @php
        $peak = max(1, $daily->max('invoiced'));
    @endphp

    <div class="table-responsive" style="max-height: 420px; overflow-y: auto">
        <table class="ad-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">Invoiced</th>
                    <th class="text-end">Received</th>
                    <th style="width:38%">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($daily as $row)
                    <tr class="{{ $row['orders'] === 0 ? 'text-muted' : '' }}">
                        <td class="small">{{ $row['day']->format('D, d M') }}</td>
                        <td class="text-end small">{{ $row['orders'] ?: '—' }}</td>
                        <td class="text-end small">{{ $row['invoiced'] > 0 ? money($row['invoiced']) : '—' }}</td>
                        <td class="text-end small">{{ $row['received'] > 0 ? money($row['received']) : '—' }}</td>
                        <td>
                            {{-- A bar drawn in CSS: no chart library to keep
                                 patched, and it prints. --}}
                            <div class="ad-bar" role="img"
                                 aria-label="{{ money($row['invoiced']) }} invoiced on {{ $row['day']->format('d M') }}">
                                <span style="width: {{ round(($row['invoiced'] / $peak) * 100, 1) }}%"></span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.card>

<div class="row g-3">
    {{-- ------------------------------------------------------- products --}}
    <div class="col-xl-6">
        <x-admin.card title="What sold" icon="bi-box-seam" :flush="true">
            <x-slot:actions>
                <a href="{{ route('admin.reports.export', request()->query() + ['report' => 'products']) }}"
                   class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="ad-table">
                    <thead>
                        <tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($topProducts as $row)
                            <tr>
                                <td class="small">
                                    {{ $row['name'] }}
                                    <div class="text-muted">{{ $row['orders'] }} order(s)</div>
                                </td>
                                <td class="text-end fw-semibold">{{ $row['units'] }}</td>
                                <td class="text-end small">{{ money($row['revenue']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted small py-3">Nothing sold in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    </div>

    {{-- ------------------------------------------------------ customers --}}
    <div class="col-xl-6">
        <x-admin.card title="Who bought" icon="bi-person-heart" :flush="true">
            <x-slot:actions>
                <a href="{{ route('admin.reports.export', request()->query() + ['report' => 'customers']) }}"
                   class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="ad-table">
                    <thead>
                        <tr><th>Customer</th><th class="text-end">Orders</th><th class="text-end">Spent</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($topCustomers as $row)
                            <tr>
                                <td class="small">
                                    {{ $row['name'] }}
                                    <div class="text-muted">{{ $row['email'] }}</div>
                                </td>
                                <td class="text-end">{{ $row['orders'] }}</td>
                                <td class="text-end fw-semibold">{{ money($row['spent']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted small py-3">Nobody bought in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    </div>
</div>

{{-- ----------------------------------------------------------- stock --}}
<x-admin.card title="Stock" icon="bi-boxes" class="mt-3">
    <div class="row g-2">
        @foreach ([
            ['label' => 'Stock at cost', 'value' => money($valuation['value']), 'sub' => $valuation['priced'].' product(s) priced', 'icon' => 'bi-cash-stack', 'tone' => 'teal'],
            ['label' => 'Units on hand', 'value' => number_format($valuation['units']), 'sub' => 'Across the catalogue', 'icon' => 'bi-boxes', 'tone' => 'navy'],
            ['label' => 'No cost price', 'value' => $valuation['unpriced'], 'sub' => 'Not counted in the value', 'icon' => 'bi-question-circle', 'tone' => 'gold'],
        ] as $stat)
            <div class="col-md-4">
                <div class="ad-stat ad-stat--{{ $stat['tone'] }}">
                    <div class="ad-stat__icon"><i class="bi {{ $stat['icon'] }}"></i></div>
                    <div>
                        <div class="ad-stat__value">{{ $stat['value'] }}</div>
                        <div class="ad-stat__label">{{ $stat['label'] }}</div>
                        <div class="text-muted" style="font-size:.74rem">{{ $stat['sub'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($valuation['unpriced'] > 0)
        <p class="small text-muted mt-3 mb-0">
            <a href="{{ route('admin.inventory.index', ['flag' => 'uncosted']) }}">{{ $valuation['unpriced'] }} active product(s)</a>
            have no cost price, so they count as nothing in the valuation. A cost price is filled
            in automatically the first time a delivery is received against them.
        </p>
    @endif
</x-admin.card>

@endsection
