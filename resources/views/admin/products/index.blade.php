@extends('admin.layouts.app')

@section('title', 'Products')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Products</li>
@endsection

@section('content')

<x-admin.card title="Product catalog" subtitle="{{ $products->total() }} product(s)" icon="bi-box-seam" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.products.export') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        @can('products.create')
            <a href="{{ route('admin.products.import.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-upload me-1"></i> Bulk Import
            </a>
            <a href="{{ route('admin.products.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Product
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-lg-3 col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search name, SKU or material">
        </div>
        <div class="col-lg-2 col-md-6">
            <select name="category" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>
                        {{ $category->full_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <select name="brand" class="form-select form-select-sm">
                <option value="">All brands</option>
                @foreach ($brands as $id => $name)
                    <option value="{{ $id }}" @selected(request('brand') == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <select name="status" class="form-select form-select-sm">
                <option value="">All status</option>
                @foreach (\App\Models\Product::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <select name="flag" class="form-select form-select-sm">
                <option value="">Any flag</option>
                <option value="featured" @selected(request('flag') === 'featured')>Featured</option>
                <option value="best_seller" @selected(request('flag') === 'best_seller')>Best seller</option>
                <option value="new_arrival" @selected(request('flag') === 'new_arrival')>New arrival</option>
                <option value="custom_order" @selected(request('flag') === 'custom_order')>Custom order</option>
            </select>
        </div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1" title="Filter"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>

    <x-admin.datatable :headers="['Product', 'Category', 'Price', 'Stock', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$products" empty="No products match this filter." empty-icon="bi-box-seam">
        @foreach ($products as $product)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        @if ($product->thumbnail_url)
                            <img src="{{ $product->thumbnail_url }}" alt=""
                                 style="width:46px;height:40px;object-fit:cover;border-radius:.3rem;background:#f1f5f9">
                        @else
                            <span class="d-grid" style="width:46px;height:40px;border-radius:.3rem;background:#f1f5f9;place-items:center">
                                <i class="bi bi-image text-muted"></i>
                            </span>
                        @endif
                        <div class="lh-sm">
                            <div class="fw-semibold">{{ $product->name }}</div>
                            <div class="text-muted" style="font-size:.72rem">
                                <code>{{ $product->sku }}</code>
                                @if ($product->variants_count)
                                    · {{ $product->variants_count }} variant(s)
                                @endif
                            </div>
                            <div class="mt-1 d-flex gap-1 flex-wrap">
                                @if ($product->is_featured)<span class="badge text-bg-warning" style="font-size:.6rem">Featured</span>@endif
                                @if ($product->is_best_seller)<span class="badge text-bg-info" style="font-size:.6rem">Best seller</span>@endif
                                @if ($product->is_new_arrival)<span class="badge text-bg-primary" style="font-size:.6rem">New</span>@endif
                                @if ($product->custom_order_allowed)<span class="badge text-bg-light border" style="font-size:.6rem">Custom order</span>@endif
                            </div>
                        </div>
                    </div>
                </td>
                <td class="lh-sm">
                    <div>{{ $product->category?->name ?? '—' }}</div>
                    <div class="text-muted" style="font-size:.72rem">{{ $product->brand?->name }}</div>
                </td>
                <td class="text-nowrap">
                    @if ($product->sale_price)
                        <span class="fw-semibold">{{ money($product->sale_price) }}</span>
                        <div class="text-muted" style="font-size:.72rem">
                            <s>{{ money($product->price) }}</s>
                            <span class="text-success">−{{ $product->discount_percent }}%</span>
                        </div>
                    @else
                        <span class="fw-semibold">{{ money($product->price) }}</span>
                    @endif
                </td>
                <td>
                    @php $stock = $product->total_stock; @endphp
                    <span class="badge {{ $stock > 0 ? 'text-bg-light border' : 'text-bg-danger' }}">
                        {{ $stock > 0 ? $stock.' in stock' : 'Out of stock' }}
                    </span>
                </td>
                <td>
                    <span class="badge text-bg-{{ $product->status_badge }}">
                        {{ \App\Models\Product::STATUSES[$product->status] }}
                    </span>
                </td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-outline-secondary"
                       data-bs-toggle="tooltip" title="Preview"><i class="bi bi-eye"></i></a>

                    @can('products.edit')
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary"
                           data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                    @endcan

                    @can('products.delete')
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline"
                              data-confirm="Remove {{ $product->name }}? The product is archived, not erased.">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Remove">
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
