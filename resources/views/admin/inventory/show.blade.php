@extends('admin.layouts.app')

@section('title', 'Stock — '.$product->name)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventory</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($product->name, 40) }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card icon="bi-clipboard-check" title="{{ $product->name }}"
                      subtitle="SKU {{ $product->sku }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $product->isOutOfStock() ? 'danger' : ($product->isLowStock() ? 'warning' : 'success') }}">
                    {{ $product->total_stock }} in stock
                </span>
            </x-slot:actions>

            @if ($product->variants->isNotEmpty())
                <div class="ad-detail__label">By variant</div>
                <div class="table-responsive">
                    <table class="ad-table">
                        <thead>
                            <tr><th>Variant</th><th class="text-end">In stock</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($product->variants as $variant)
                                <tr>
                                    <td class="small">{{ collect([$variant->size, $variant->color])->filter()->implode(' / ') ?: 'Variant #'.$variant->id }}</td>
                                    <td class="text-end fw-semibold">{{ $variant->stock_quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    With variants, the product total is the sum of these; the product's own
                    figure is not used.
                </p>
            @endif
        </x-admin.card>

        {{-- --------------------------------------------------- the ledger --}}
        <x-admin.card icon="bi-clock-history" title="Ledger"
                      subtitle="Every change, most recent first" class="mt-3" :flush="true">
            <x-admin.datatable :headers="['When', 'What happened', 'Change', 'Balance', 'Who']"
                               :rows="$movements" empty="Nothing has moved yet." empty-icon="bi-clock-history">
                @foreach ($movements as $movement)
                    <tr>
                        <td class="small text-muted text-nowrap">
                            {{ $movement->created_at->format('d M Y') }}
                            <div style="font-size:.72rem">{{ $movement->created_at->format('H:i') }}</div>
                        </td>

                        <td class="small">
                            <span class="badge text-bg-light border">{{ $movement->type_label }}</span>
                            @if ($movement->reference_label)
                                <div class="text-muted">{{ $movement->reference_label }}</div>
                            @endif
                            @if ($movement->reason)
                                <div class="text-muted">{{ $movement->reason }}</div>
                            @endif
                            @if ($movement->variant)
                                <div class="text-muted">
                                    {{ collect([$movement->variant->size, $movement->variant->color])->filter()->implode(' / ') }}
                                </div>
                            @endif
                        </td>

                        <td class="fw-semibold text-{{ $movement->tone }}">{{ $movement->signed_quantity }}</td>

                        <td class="fw-semibold">{{ $movement->balance_after }}</td>

                        <td class="small text-muted">{{ $movement->actor }}</td>
                    </tr>
                @endforeach
            </x-admin.datatable>
        </x-admin.card>
    </div>

    {{-- ------------------------------------------------------ adjusting --}}
    <div class="col-xl-4">
        @can('inventory.edit')
            <x-admin.card icon="bi-pencil-square" title="Correct the count">
                <p class="small text-muted">
                    There is no editing the number directly. Record what you counted, or what
                    was written off, and the ledger keeps the reason.
                </p>

                <form method="POST" action="{{ route('admin.inventory.adjust', $product) }}">
                    @csrf

                    @if ($product->variants->isNotEmpty())
                        <div class="mb-2">
                            <label for="product_variant_id" class="form-label">Which variant</label>
                            <select id="product_variant_id" name="product_variant_id" class="form-select form-select-sm" required>
                                @foreach ($product->variants as $variant)
                                    <option value="{{ $variant->id }}">
                                        {{ collect([$variant->size, $variant->color])->filter()->implode(' / ') ?: 'Variant #'.$variant->id }}
                                        — {{ $variant->stock_quantity }} in stock
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mb-2">
                        <label for="mode" class="form-label">What are you recording?</label>
                        <select id="mode" name="mode" class="form-select form-select-sm">
                            <option value="count" @selected(old('mode', 'count') === 'count')>A count — I counted this many</option>
                            <option value="change" @selected(old('mode') === 'change')>A change — add or take away this many</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="counted_quantity" class="form-label">Counted</label>
                            <input type="number" id="counted_quantity" name="counted_quantity" min="0"
                                   value="{{ old('counted_quantity') }}"
                                   class="form-control form-control-sm @error('counted_quantity') is-invalid @enderror">
                            @error('counted_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label for="quantity" class="form-label">Change by</label>
                            <input type="number" id="quantity" name="quantity"
                                   value="{{ old('quantity') }}" placeholder="-3"
                                   class="form-control form-control-sm @error('quantity') is-invalid @enderror">
                            @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="type" class="form-label">Kind</label>
                        <select id="type" name="type" class="form-select form-select-sm">
                            <option value="adjustment" @selected(old('type', 'adjustment') === 'adjustment')>Stock count adjustment</option>
                            <option value="damage" @selected(old('type') === 'damage')>Damaged or written off</option>
                            <option value="correction" @selected(old('type') === 'correction')>Correcting a mistake</option>
                            <option value="opening" @selected(old('type') === 'opening')>Opening balance</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">Why <span class="text-danger">*</span></label>
                        <input type="text" id="reason" name="reason" maxlength="200" required
                               value="{{ old('reason') }}" placeholder="Monthly count, water damage in the store…"
                               class="form-control form-control-sm @error('reason') is-invalid @enderror">
                        @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button class="btn btn-sm btn-primary w-100">Record it</button>
                </form>
            </x-admin.card>

            <x-admin.card icon="bi-sliders" title="Reorder level &amp; cost" class="mt-3">
                <form method="POST" action="{{ route('admin.inventory.threshold', $product) }}">
                    @csrf @method('PATCH')

                    <label for="low_stock_threshold" class="form-label">Warn me below</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0"
                           value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}"
                           placeholder="{{ settings('low_stock_threshold', 5) }} (shop default)"
                           class="form-control form-control-sm mb-2">

                    <label for="cost_price" class="form-label">Cost price</label>
                    <input type="number" step="0.01" min="0" id="cost_price" name="cost_price"
                           value="{{ old('cost_price', $product->cost_price) }}"
                           class="form-control form-control-sm mb-2">
                    <div class="form-text mb-2" style="font-size:.72rem">
                        Used for stock valuation. Updated automatically when a delivery is received.
                    </div>

                    <button class="btn btn-sm btn-outline-secondary w-100">Save</button>
                </form>
            </x-admin.card>
        @endcan

        <x-admin.card icon="bi-box-arrow-up-right" title="The product" class="mt-3">
            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary w-100">
                Open in the catalogue
            </a>
        </x-admin.card>
    </div>
</div>

@endsection
