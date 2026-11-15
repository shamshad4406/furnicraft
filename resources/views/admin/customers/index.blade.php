@extends('admin.layouts.app')

@section('title', 'Customers')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Customers</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'total',        'label' => 'Accounts',        'icon' => 'bi-person-heart',  'tone' => 'navy', 'query' => []],
        ['key' => 'withOrders',   'label' => 'Have bought',     'icon' => 'bi-bag-check',     'tone' => 'teal', 'query' => ['flag' => 'buyers']],
        ['key' => 'newThisMonth', 'label' => 'Joined this month','icon' => 'bi-person-plus',  'tone' => 'gold', 'query' => []],
        ['key' => 'active',       'label' => 'Can sign in',     'icon' => 'bi-check2-circle', 'tone' => 'teal', 'query' => ['status' => 'active']],
    ] as $tile)
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.customers.index', $tile['query']) }}"
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

<x-admin.card title="Customers" subtitle="{{ $customers->total() }} account(s)" icon="bi-person-heart" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.customers.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Name, email or phone">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Can sign in</option>
                <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="flag" class="form-select form-select-sm">
                <option value="">Everyone</option>
                <option value="buyers" @selected(request('flag') === 'buyers')>Have bought something</option>
                <option value="never_ordered" @selected(request('flag') === 'never_ordered')>Never ordered</option>
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Customer', 'Contact', 'Orders', 'Spent', 'Last order', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$customers" empty="No customers match this filter." empty-icon="bi-person-heart">
        @foreach ($customers as $customer)
            <tr>
                <td>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="fw-semibold text-decoration-none">
                        {{ $customer->name }}
                    </a>
                    <div class="small text-muted">Joined {{ $customer->created_at->format('d M Y') }}</div>
                </td>

                <td class="small">
                    {{ $customer->email }}
                    <div class="text-muted">{{ $customer->phone }}</div>
                </td>

                <td class="small">
                    {{ $customer->orders_count }}
                    @if ($customer->addresses_count > 0)
                        <div class="text-muted">{{ $customer->addresses_count }} address(es)</div>
                    @endif
                </td>

                <td class="fw-semibold text-nowrap">{{ money($customer->spent ?? 0) }}</td>

                <td class="small text-muted">
                    {{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d M Y') : '—' }}
                </td>

                <td>
                    <span class="badge text-bg-{{ $customer->is_active ? 'success' : 'danger' }}">
                        {{ $customer->is_active ? 'Active' : 'Disabled' }}
                    </span>
                </td>

                <td class="text-end">
                    <a href="{{ route('admin.customers.show', $customer) }}"
                       class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
