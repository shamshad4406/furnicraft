@extends('admin.layouts.app')

@section('title', $product->name)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Products</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($product->name, 40) }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Images" icon="bi-images">
            @if ($product->images->isEmpty())
                <div class="ad-empty"><i class="bi bi-image"></i>No images uploaded.</div>
            @else
                <img src="{{ $product->images->firstWhere('is_primary', true)?->url ?? $product->images->first()->url }}"
                     alt="{{ $product->name }}" class="w-100 rounded mb-2"
                     style="max-height:320px;object-fit:contain;background:#f4f6f9">

                <div class="d-flex gap-2 flex-wrap">
                    @foreach ($product->images as $image)
                        <img src="{{ $image->thumbnail_url }}" alt="{{ $image->alt_text }}"
                             class="rounded border {{ $image->is_primary ? 'border-warning border-2' : '' }}"
                             style="width:62px;height:52px;object-fit:cover;background:#f4f6f9">
                    @endforeach
                </div>
            @endif
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.card :title="$product->name" :subtitle="$product->sku" icon="bi-box-seam">
            <x-slot:actions>
                @can('products.edit')
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                @endcan
            </x-slot:actions>

            <div class="d-flex gap-2 flex-wrap mb-3">
                <span class="badge text-bg-{{ $product->status_badge }}">{{ \App\Models\Product::STATUSES[$product->status] }}</span>
                @if ($product->is_featured)<span class="badge text-bg-warning">Featured</span>@endif
                @if ($product->is_best_seller)<span class="badge text-bg-info">Best seller</span>@endif
                @if ($product->is_new_arrival)<span class="badge text-bg-primary">New arrival</span>@endif
                @if ($product->custom_order_allowed)<span class="badge text-bg-light border">Custom order</span>@endif
            </div>

            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span class="fs-4 fw-bold" style="color:var(--fc-navy)">{{ money($product->effective_price) }}</span>
                @if ($product->sale_price)
                    <s class="text-muted">{{ money($product->price) }}</s>
                    <span class="badge text-bg-success">−{{ $product->discount_percent }}%</span>
                @endif
                <span class="text-muted small ms-auto">GST {{ rtrim(rtrim((string) $product->gst_rate, '0'), '.') }}%</span>
            </div>

            @if ($product->short_description)
                <p class="text-muted">{{ $product->short_description }}</p>
            @endif

            <dl class="row mb-0 small">
                <dt class="col-5 text-muted fw-normal">Category</dt>
                <dd class="col-7">{{ $product->category?->full_name ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Brand</dt>
                <dd class="col-7">{{ $product->brand?->name ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Material</dt>
                <dd class="col-7">{{ $product->material ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Dimensions</dt>
                <dd class="col-7">{{ $product->dimensions ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Weight</dt>
                <dd class="col-7">{{ $product->weight ? $product->weight.' kg' : '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Warranty</dt>
                <dd class="col-7">{{ $product->warranty ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">HSN code</dt>
                <dd class="col-7">{{ $product->hsn_code ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Total stock</dt>
                <dd class="col-7 mb-0">{{ $product->total_stock }}</dd>
            </dl>
        </x-admin.card>
    </div>

    @if ($product->variants->isNotEmpty())
        <div class="col-lg-7">
            <x-admin.card title="Variants" icon="bi-palette" :flush="true">
                <x-admin.datatable :headers="['SKU', 'Size', 'Colour', 'Price', 'Stock', 'Active']">
                    @foreach ($product->variants as $variant)
                        <tr>
                            <td><code>{{ $variant->sku }}</code></td>
                            <td>{{ $variant->size ?? '—' }}</td>
                            <td>{{ $variant->color ?? '—' }}</td>
                            <td>{{ money($variant->effective_price) }}</td>
                            <td>{{ $variant->stock_quantity }}</td>
                            <td>
                                <span class="badge {{ $variant->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $variant->is_active ? 'Yes' : 'No' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.datatable>
            </x-admin.card>
        </div>
    @endif

    @if ($product->specifications->isNotEmpty())
        <div class="col-lg-5">
            <x-admin.card title="Specifications" icon="bi-list-columns" :flush="true">
                <x-admin.datatable :headers="['Specification', 'Value']">
                    @foreach ($product->specifications as $spec)
                        <tr>
                            <td class="text-muted">{{ $spec->name }}</td>
                            <td class="fw-semibold">{{ $spec->value }}</td>
                        </tr>
                    @endforeach
                </x-admin.datatable>
            </x-admin.card>
        </div>
    @endif

    @if ($product->description)
        <div class="col-12">
            <x-admin.card title="Description" icon="bi-file-text">
                <div style="white-space:pre-line">{{ $product->description }}</div>
            </x-admin.card>
        </div>
    @endif
</div>

@endsection
