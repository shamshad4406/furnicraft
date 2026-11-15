@extends('admin.layouts.app')

@section('title', 'GST summary')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">GST</li>
@endsection

@section('content')

<x-admin.card title="GST summary" icon="bi-receipt" :flush="true"
              subtitle="From invoices raised in this period">
    <x-slot:actions>
        <a href="{{ route('admin.reports.export', request()->query() + ['report' => 'gst']) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> CSV
        </a>
    </x-slot:actions>

    @include('admin.reports._period', ['action' => route('admin.reports.gst')])

    <div class="table-responsive">
        <table class="ad-table">
            <thead>
                <tr>
                    <th>Rate</th>
                    <th>Supply</th>
                    <th class="text-end">Taxable value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end">IGST</th>
                    <th class="text-end">Total tax</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}%</td>
                        <td class="small text-muted">{{ $row['interstate'] ? 'Inter-state' : 'Intra-state' }}</td>
                        <td class="text-end">{{ number_format($row['taxable'], 2) }}</td>
                        <td class="text-end">{{ $row['cgst'] > 0 ? number_format($row['cgst'], 2) : '—' }}</td>
                        <td class="text-end">{{ $row['sgst'] > 0 ? number_format($row['sgst'], 2) : '—' }}</td>
                        <td class="text-end">{{ $row['igst'] > 0 ? number_format($row['igst'], 2) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted small py-4">
                            No invoices were raised in this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="2" class="fw-semibold">Total</td>
                        <td class="text-end fw-semibold">{{ number_format($rows->sum('taxable'), 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($rows->sum('cgst'), 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($rows->sum('sgst'), 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($rows->sum('igst'), 2) }}</td>
                        <td class="text-end fw-bold">{{ number_format($rows->sum('total'), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="ad-card__body border-top">
        <p class="small text-muted mb-0">
            Counted by <strong>invoice date</strong>, not order date: GST is owed on invoices raised.
            An order with no invoice yet does not appear here, and cancelled or returned orders are
            left out. Prices are GST-inclusive, so the taxable value is the line less the tax inside it.
        </p>
    </div>
</x-admin.card>

@endsection
