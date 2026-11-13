@extends('admin.layouts.app')

@section('title', 'Brands')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Brands</li>
@endsection

@section('content')

<x-admin.card title="Brands" subtitle="{{ $brands->total() }} brand(s)" icon="bi-award" :flush="true">
    <x-slot:actions>
        @can('brands.create')
            <a href="{{ route('admin.brands.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Brand
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-9">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search name or slug">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
            <a href="{{ route('admin.brands.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <x-admin.datatable :headers="['Brand', 'Slug', 'Products', 'Order', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$brands" empty="No brands yet. Brands are optional." empty-icon="bi-award">
        @foreach ($brands as $brand)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        @if ($brand->logo_url)
                            <img src="{{ $brand->logo_url }}" alt=""
                                 style="width:40px;height:28px;object-fit:contain;background:#f1f5f9;border-radius:.25rem">
                        @else
                            <span class="d-grid" style="width:40px;height:28px;border-radius:.25rem;background:#f1f5f9;place-items:center">
                                <i class="bi bi-award text-muted small"></i>
                            </span>
                        @endif
                        <span class="fw-semibold">{{ $brand->name }}</span>
                    </div>
                </td>
                <td class="text-muted" style="font-size:.8rem"><code>{{ $brand->slug }}</code></td>
                <td><span class="badge text-bg-light border">{{ $brand->products_count }}</span></td>
                <td class="text-muted">{{ $brand->sort_order }}</td>
                <td>
                    <span class="badge {{ $brand->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $brand->is_active ? 'Active' : 'Hidden' }}
                    </span>
                </td>
                <td class="text-end text-nowrap">
                    @can('brands.edit')
                        <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-sm btn-outline-secondary"
                           data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="{{ route('admin.brands.toggle', $brand) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-{{ $brand->is_active ? 'warning' : 'success' }}"
                                    data-bs-toggle="tooltip" title="{{ $brand->is_active ? 'Hide' : 'Show' }}">
                                <i class="bi {{ $brand->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                            </button>
                        </form>
                    @endcan

                    @can('brands.delete')
                        <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="d-inline"
                              data-confirm="Delete the brand {{ $brand->name }}?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
