@extends('front.layouts.app')

@php
    $metaDescription = (string) ($product->meta_description
        ?: $product->short_description
        ?: Str::limit((string) $product->description, 150)
        ?: settings('company_about', ''));

    $gallery = $product->images;
    $primary = $gallery->firstWhere('is_primary', true) ?? $gallery->first();

    // Variant data for the live price/stock selector.
    $variantData = $product->variants->map(fn ($v) => [
        'id' => $v->id,
        'sku' => $v->sku,
        'size' => $v->size,
        'color' => $v->color,
        'label' => $v->label,
        'price' => (float) $v->effective_price,
        'priceLabel' => money($v->effective_price),
        'stock' => (int) $v->stock_quantity,
    ])->values();
@endphp

@section('title', (string) ($product->meta_title ?: $product->name))
@section('meta_description', $metaDescription)

@section('content')

<section class="fc-section fc-product">
    <div class="container-fc">

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb fc-product__crumbs">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('categories') }}">Categories</a></li>
                @if ($product->category?->parent)
                    <li class="breadcrumb-item">
                        <a href="{{ route('category.show', $product->category->parent->slug) }}">{{ $product->category->parent->name }}</a>
                    </li>
                @endif
                @if ($product->category)
                    <li class="breadcrumb-item">
                        <a href="{{ route('category.show', $product->category->slug) }}">{{ $product->category->name }}</a>
                    </li>
                @endif
                <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($product->name, 40) }}</li>
            </ol>
        </nav>

        <div class="row g-4">
            {{-- ------------------------------------------------- gallery --}}
            <div class="col-lg-6">
                <div class="fc-gallery" data-gallery>
                    <div class="fc-gallery__stage">
                        <img id="galleryMain"
                             src="{{ $primary?->url ?? asset('images/logo.svg') }}"
                             alt="{{ $primary?->alt_text ?: $product->name }}"
                             data-zoom>
                        @if ($product->discount_percent)
                            <span class="fc-gallery__badge">−{{ $product->discount_percent }}% off</span>
                        @endif
                        <span class="fc-gallery__hint"><i class="bi bi-zoom-in"></i> Hover to zoom</span>
                    </div>

                    @if ($gallery->count() > 1)
                        <div class="fc-gallery__thumbs">
                            @foreach ($gallery as $image)
                                <button type="button"
                                        class="fc-gallery__thumb {{ $image->id === $primary?->id ? 'is-active' : '' }}"
                                        data-full="{{ $image->url }}"
                                        data-alt="{{ $image->alt_text ?: $product->name }}"
                                        aria-label="View image {{ $loop->iteration }}">
                                    <img src="{{ $image->thumbnail_url }}" alt="" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- --------------------------------------------------- detail --}}
            <div class="col-lg-6">
                <div class="fc-product__flags">
                    @if ($product->is_new_arrival)<span class="badge text-bg-primary">New arrival</span>@endif
                    @if ($product->is_best_seller)<span class="badge text-bg-info">Best seller</span>@endif
                    @if ($product->custom_order_allowed)<span class="badge text-bg-warning">Custom order allowed</span>@endif
                </div>

                <h1 class="fc-product__title">{{ $product->name }}</h1>

                <div class="fc-product__sub">
                    <span>SKU <code>{{ $product->sku }}</code></span>
                    @if ($product->brand)
                        <span>·</span><span>{{ $product->brand->name }}</span>
                    @endif
                </div>

                @if ($product->short_description)
                    <p class="fc-product__lead">{{ $product->short_description }}</p>
                @endif

                <div class="fc-product__price" data-price-box>
                    <span class="fc-product__price-now" data-price-now>{{ money($product->effective_price) }}</span>
                    @if ($product->sale_price)
                        <s class="fc-product__price-was">{{ money($product->price) }}</s>
                        <span class="badge text-bg-success">Save {{ money($product->price - $product->sale_price) }}</span>
                    @endif
                    <span class="fc-product__tax">incl. {{ rtrim(rtrim((string) $product->gst_rate, '0'), '.') }}% GST</span>
                </div>

                <div class="fc-product__stock" data-stock-line>
                    @if ($product->total_stock > 0)
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>In stock — {{ $product->total_stock }} available</span>
                    @else
                        <i class="bi bi-x-circle-fill text-danger"></i>
                        <span>Currently out of stock — send an enquiry for availability</span>
                    @endif
                </div>

                {{-- ----------------------------------------- variants --}}
                @if ($product->variants->isNotEmpty())
                    <div class="fc-variants" data-variants='@json($variantData)'>
                        <span class="fc-variants__label">Choose an option</span>
                        <div class="fc-variants__list">
                            @foreach ($product->variants as $variant)
                                <button type="button" class="fc-variant {{ $loop->first ? 'is-active' : '' }}"
                                        data-variant-id="{{ $variant->id }}"
                                        @disabled($variant->stock_quantity <= 0)>
                                    <span class="fc-variant__label">{{ $variant->label }}</span>
                                    <span class="fc-variant__price">{{ money($variant->effective_price) }}</span>
                                </button>
                            @endforeach
                        </div>
                        <div class="fc-variants__sku text-muted" data-variant-sku></div>
                    </div>
                @endif

                {{-- ------------------------------------ pincode check --}}
                <div class="fc-pincode" data-pincode-check data-url="{{ route('delivery.check') }}">
                    <label for="pincode" class="fc-pincode__label">
                        <i class="bi bi-geo-alt-fill"></i> Check delivery to your pincode
                    </label>
                    <div class="fc-pincode__row">
                        <input type="text" id="pincode" inputmode="numeric" maxlength="6"
                               class="form-control" placeholder="e.g. 110001"
                               value="{{ session('pincode') }}" data-pincode-input>
                        <button type="button" class="btn btn-navy" data-pincode-submit>Check</button>
                    </div>
                    <div class="fc-pincode__result" data-pincode-result role="status" aria-live="polite"></div>
                </div>

                <div class="fc-product__actions">
                    <a href="{{ route('page', ['slug' => 'contact-us', 'product' => $product->slug]) }}"
                       class="btn btn-gold btn-lg d-inline-flex align-items-center gap-2">
                        <i class="bi bi-envelope-fill"></i> Send Enquiry
                    </a>
                    @if (settings('company_whatsapp'))
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', settings('company_whatsapp')) }}?text={{ urlencode('Hi, I am interested in '.$product->name.' ('.$product->sku.')') }}"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-outline-success btn-lg d-inline-flex align-items-center gap-2">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    @endif
                </div>

                {{-- ------------------------------------- at-a-glance --}}
                <dl class="fc-product__facts">
                    @foreach ([
                        'Material' => $product->material,
                        'Colour' => $product->color,
                        'Dimensions' => $product->dimensions,
                        'Weight' => $product->weight ? $product->weight.' kg' : null,
                        'Warranty' => $product->warranty,
                    ] as $label => $value)
                        @if ($value)
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- ------------------------------------ description & specs --}}
        @if ($product->description || $product->specifications->isNotEmpty())
            <div class="row g-4 mt-1">
                @if ($product->description)
                    <div class="col-lg-7">
                        <div class="fc-panel">
                            <h2 class="fc-panel__title">Product details</h2>
                            <div class="fc-prose">{!! nl2br(e($product->description)) !!}</div>
                        </div>
                    </div>
                @endif

                @if ($product->specifications->isNotEmpty())
                    <div class="col-lg-5">
                        <div class="fc-panel">
                            <h2 class="fc-panel__title">Specifications</h2>
                            <table class="fc-spec-table">
                                <tbody>
                                    @foreach ($product->specifications as $spec)
                                        <tr>
                                            <th scope="row">{{ $spec->name }}</th>
                                            <td>{{ $spec->value }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @include('front.partials.product-row', ['title' => 'Related products', 'items' => $related])
        @include('front.partials.product-row', ['title' => 'Recently viewed', 'items' => $recentlyViewed])
    </div>
</section>

@endsection
