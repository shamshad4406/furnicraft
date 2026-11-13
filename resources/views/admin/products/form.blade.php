@extends('admin.layouts.app')

@php
    $editing = $product->exists;
    $oldVariants = old('variants', $editing ? $product->variants->toArray() : []);
    $oldSpecs = old('specifications', $editing ? $product->specifications->toArray() : []);
@endphp

@section('title', $editing ? 'Edit Product' : 'Add Product')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Products</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? Str::limit($product->name, 40) : 'Add' }}</li>
@endsection

@section('content')

<form method="POST"
      action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
      enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        {{-- ------------------------------------------------------ main --}}
        <div class="col-xl-8">
            <x-admin.card title="Basic information" icon="bi-box-seam">
                <div class="row g-3">
                    <x-admin.input name="name" label="Product name" :value="$product->name" required col="col-md-8" />
                    <x-admin.input name="sku" label="SKU" :value="$product->sku" col="col-md-4"
                                   help="Leave blank to generate one." />

                    <x-admin.select name="category_id" label="Category" col="col-md-4" required
                                    :value="$product->category_id" placeholder="Select a category">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}"
                                @selected((string) old('category_id', $product->category_id) === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                            @foreach ($category->children as $child)
                                <option value="{{ $child->id }}"
                                    @selected((string) old('category_id', $product->category_id) === (string) $child->id)>
                                    &nbsp;&nbsp;— {{ $child->name }}
                                </option>
                            @endforeach
                        @endforeach
                    </x-admin.select>

                    <x-admin.select name="brand_id" label="Brand" col="col-md-4" :options="$brands->all()"
                                    :value="$product->brand_id" placeholder="— None —" />
                    <x-admin.input name="slug" label="URL slug" :value="$product->slug" col="col-md-4"
                                   help="Blank = from the name." />

                    <x-admin.textarea name="short_description" label="Short description" rows="2"
                                      :value="$product->short_description"
                                      help="One or two lines, shown on listing pages." />
                    <x-admin.textarea name="description" label="Full description" rows="6" :value="$product->description" />
                </div>
            </x-admin.card>

            <x-admin.card title="Furniture details" icon="bi-rulers" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="material" label="Material" :value="$product->material" col="col-md-4"
                                   placeholder="Sheesham Wood" />
                    <x-admin.input name="color" label="Colour" :value="$product->color" col="col-md-4"
                                   placeholder="Walnut Brown" />
                    <x-admin.input name="dimensions" label="Dimensions" :value="$product->dimensions" col="col-md-4"
                                   placeholder="180 x 90 x 75 cm" />
                    <x-admin.input name="weight" label="Weight (kg)" type="number" step="0.01" min="0"
                                   :value="$product->weight" col="col-md-4" />
                    <x-admin.input name="warranty" label="Warranty" :value="$product->warranty" col="col-md-4"
                                   placeholder="2 Years" />
                    <x-admin.input name="sort_order" label="Sort order" type="number" min="0"
                                   :value="$product->sort_order ?? 0" col="col-md-4" />
                </div>
            </x-admin.card>

            {{-- -------------------------------------------- specifications --}}
            <x-admin.card title="Specifications" subtitle="Key-value rows shown on the product page" icon="bi-list-columns" class="mt-3">
                <x-slot:actions>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-add="#specRows">
                        <i class="bi bi-plus-lg me-1"></i> Add row
                    </button>
                </x-slot:actions>

                <div id="specRows" data-repeat>
                    @forelse ($oldSpecs as $i => $spec)
                        @include('admin.products._spec-row', ['i' => $i, 'spec' => $spec])
                    @empty
                        @include('admin.products._spec-row', ['i' => 0, 'spec' => []])
                    @endforelse
                </div>

                <template data-repeat-template="#specRows">
                    @include('admin.products._spec-row', ['i' => '__INDEX__', 'spec' => []])
                </template>
            </x-admin.card>

            {{-- -------------------------------------------------- variants --}}
            <x-admin.card title="Variants" subtitle="Size and colour options, each with its own SKU, price and stock"
                          icon="bi-palette" class="mt-3">
                <x-slot:actions>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-add="#variantRows">
                        <i class="bi bi-plus-lg me-1"></i> Add variant
                    </button>
                </x-slot:actions>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="min-width:760px">
                        <thead>
                            <tr class="text-muted" style="font-size:.72rem;text-transform:uppercase">
                                <th style="width:20%">SKU</th>
                                <th style="width:15%">Size</th>
                                <th style="width:15%">Colour</th>
                                <th style="width:14%">Price</th>
                                <th style="width:14%">Sale price</th>
                                <th style="width:12%">Stock</th>
                                <th style="width:6%">Active</th>
                                <th style="width:4%"></th>
                            </tr>
                        </thead>
                        <tbody id="variantRows" data-repeat>
                            @forelse ($oldVariants as $i => $variant)
                                @include('admin.products._variant-row', ['i' => $i, 'variant' => $variant])
                            @empty
                                @include('admin.products._variant-row', ['i' => 0, 'variant' => []])
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <template data-repeat-template="#variantRows">
                    @include('admin.products._variant-row', ['i' => '__INDEX__', 'variant' => []])
                </template>

                <div class="form-text mt-2">
                    Leave this empty for a simple product — the stock field on the right is used instead.
                </div>
            </x-admin.card>

            <x-admin.card title="SEO" icon="bi-search" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="meta_title" label="Meta title" :value="$product->meta_title" col="col-12" />
                    <x-admin.textarea name="meta_description" label="Meta description" rows="2" :value="$product->meta_description" />
                </div>
            </x-admin.card>
        </div>

        {{-- ----------------------------------------------------- sidebar --}}
        <div class="col-xl-4">
            <x-admin.card title="Pricing & tax" icon="bi-currency-rupee">
                <div class="row g-3">
                    <x-admin.input name="price" label="Price ({{ settings('currency_symbol', '₹') }})" type="number"
                                   step="0.01" min="0" :value="$product->price" required col="col-6" />
                    <x-admin.input name="sale_price" label="Sale price" type="number" step="0.01" min="0"
                                   :value="$product->sale_price" col="col-6"
                                   help="Must be lower than the price." />
                    <x-admin.input name="gst_rate" label="GST %" type="number" step="0.01" min="0" max="28"
                                   :value="$product->gst_rate" required col="col-6" />
                    <x-admin.input name="hsn_code" label="HSN code" :value="$product->hsn_code" col="col-6"
                                   placeholder="94036000" />
                    <x-admin.input name="stock_quantity" label="Stock quantity" type="number" min="0"
                                   :value="$product->stock_quantity ?? 0" col="col-12"
                                   help="Ignored when the product has variants. Full stock control arrives in Phase 7." />
                </div>
            </x-admin.card>

            <x-admin.card title="Status & flags" icon="bi-toggles" class="mt-3">
                <div class="row g-3">
                    <x-admin.select name="status" label="Status" col="col-12" required
                                    :options="$statuses" :value="$product->status ?? 'draft'"
                                    help="Only active products appear on the website." />

                    <x-admin.switcher name="is_featured" col="col-12" :checked="$product->is_featured">Featured product</x-admin.switcher>
                    <x-admin.switcher name="is_best_seller" col="col-12" :checked="$product->is_best_seller">Best seller</x-admin.switcher>
                    <x-admin.switcher name="is_new_arrival" col="col-12" :checked="$product->is_new_arrival">New arrival</x-admin.switcher>
                    <x-admin.switcher name="custom_order_allowed" col="col-12" :checked="$product->custom_order_allowed">
                        Custom order allowed
                    </x-admin.switcher>
                </div>
            </x-admin.card>

            @unless ($editing)
                <x-admin.card title="Images" icon="bi-images" class="mt-3">
                    <input type="file" name="images[]" accept="image/*" multiple
                           class="form-control form-control-sm @error('images.*') is-invalid @enderror">
                    @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="form-text">
                        Up to 10 images, 8 MB each. Converted to WebP with thumbnails automatically.
                        The first one becomes the primary image.
                    </div>
                </x-admin.card>
            @endunless

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Changes' : 'Create Product' }}
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

{{-- Image manager lives outside the main form so uploads post on their own. --}}
@if ($editing)
    <div class="row g-3 mt-0">
        <div class="col-12">
            @include('admin.products._images')
        </div>
    </div>
@endif

@endsection
