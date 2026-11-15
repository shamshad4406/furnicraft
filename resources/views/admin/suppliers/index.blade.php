@extends('admin.layouts.app')

@section('title', 'Suppliers')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Suppliers</li>
@endsection

@section('content')

<x-admin.card title="Suppliers" subtitle="{{ $suppliers->total() }} supplier(s)" icon="bi-truck-front" :flush="true">
    <x-slot:actions>
        @can('suppliers.create')
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add supplier
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-8">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Name, code, contact, phone, email or GSTIN">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All suppliers</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Supplier', 'Contact', 'Terms', 'Orders', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$suppliers" empty="No suppliers yet." empty-icon="bi-truck-front">
        @foreach ($suppliers as $supplier)
            <tr>
                <td>
                    <a href="{{ route('admin.suppliers.show', $supplier) }}" class="fw-semibold text-decoration-none">
                        {{ $supplier->name }}
                    </a>
                    <div class="small text-muted"><code>{{ $supplier->code }}</code></div>
                    @if ($supplier->gst_number)
                        <div class="small text-muted">{{ $supplier->gst_number }}</div>
                    @endif
                </td>

                <td class="small">
                    {{ $supplier->contact_person ?: '—' }}
                    @if ($supplier->phone)
                        <div class="text-muted">{{ $supplier->phone }}</div>
                    @endif
                    @if ($supplier->city)
                        <div class="text-muted">{{ $supplier->city }}</div>
                    @endif
                </td>

                <td class="small text-muted">
                    @if ($supplier->payment_terms_days !== null)
                        Net {{ $supplier->payment_terms_days }} days
                    @endif
                    @if ($supplier->lead_time_days !== null)
                        <div>{{ $supplier->lead_time_days }} day lead time</div>
                    @endif
                </td>

                <td class="small">{{ $supplier->purchase_orders_count }}</td>

                <td>
                    <span class="badge text-bg-{{ $supplier->is_active ? 'success' : 'secondary' }}">
                        {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>

                <td class="text-end">
                    @can('purchases.create')
                        @if ($supplier->is_active)
                            <a href="{{ route('admin.purchases.create', ['supplier' => $supplier->id]) }}"
                               class="btn btn-sm btn-outline-secondary" title="New purchase order">
                                <i class="bi bi-cart-plus"></i>
                            </a>
                        @endif
                    @endcan
                    @can('suppliers.edit')
                        <a href="{{ route('admin.suppliers.edit', $supplier) }}"
                           class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
