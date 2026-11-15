@extends('front.layouts.app')

@section('title', 'Your Cart')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Your Cart</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cart</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">

        @if ($problems || session('cart_problems'))
            <div class="alert alert-warning">
                <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Please check these before continuing</strong>
                <ul class="mb-0 mt-2 ps-3 small">
                    @foreach (array_merge($problems, (array) session('cart_problems', [])) as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($cart->isEmpty())
            <div class="fc-empty-state">
                <i class="bi bi-cart-x"></i>
                <h2>Your cart is empty</h2>
                <p>Browse the catalog and add something you like.</p>
                <a href="{{ route('products') }}" class="btn btn-navy btn-sm">Shop products</a>
            </div>
        @else
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="fc-cart-list">
                        @foreach ($cart->items as $item)
                            <div class="fc-cart-row">
                                <a href="{{ route('product.show', $item->product->slug) }}" class="fc-cart-row__media">
                                    <img src="{{ $item->product->thumbnail_url ?? asset('images/logo.svg') }}"
                                         alt="{{ $item->product->name }}" loading="lazy">
                                </a>

                                <div class="fc-cart-row__detail">
                                    <a href="{{ route('product.show', $item->product->slug) }}" class="fc-cart-row__name">
                                        {{ $item->product->name }}
                                    </a>

                                    @if ($item->label)
                                        <div class="fc-cart-row__variant">{{ $item->label }}</div>
                                    @endif

                                    <div class="fc-cart-row__sku">SKU {{ $item->variant->sku ?? $item->product->sku }}</div>

                                    @if ($item->price_changed)
                                        <div class="fc-cart-row__warning">
                                            Price changed from {{ money($item->unit_price) }} to {{ money($item->current_price) }}
                                        </div>
                                    @endif

                                    @if ($item->available_stock < $item->quantity)
                                        <div class="fc-cart-row__warning">
                                            Only {{ $item->available_stock }} left in stock
                                        </div>
                                    @endif
                                </div>

                                <div class="fc-cart-row__qty">
                                    <form method="POST" action="{{ route('cart.update', $item) }}" class="d-flex gap-1">
                                        @csrf @method('PATCH')
                                        <input type="number" name="quantity" value="{{ $item->quantity }}"
                                               min="1" max="{{ \App\Services\CartService::MAX_QUANTITY }}"
                                               class="form-control form-control-sm" aria-label="Quantity">
                                        <button class="btn btn-sm btn-outline-secondary" title="Update">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                </div>

                                <div class="fc-cart-row__price">
                                    <strong>{{ money($item->line_subtotal) }}</strong>
                                    <small>{{ money($item->current_price) }} each</small>
                                </div>

                                <form method="POST" action="{{ route('cart.remove', $item) }}"
                                      class="fc-cart-row__remove" data-confirm="Remove {{ $item->product->name }} from your cart?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger" title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                        <a href="{{ route('products') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Continue shopping
                        </a>
                    </div>
                </div>

                <div class="col-lg-4">
                    {{-- Pincode, so delivery can be costed before checkout. --}}
                    <div class="fc-summary mb-3">
                        <h2 class="fc-summary__title">Delivery</h2>
                        <form method="POST" action="{{ route('cart.pincode') }}" class="d-flex gap-2">
                            @csrf
                            <input type="text" name="pincode" value="{{ $pincode }}" maxlength="6" inputmode="numeric"
                                   class="form-control form-control-sm" placeholder="Delivery pincode"
                                   aria-label="Delivery pincode">
                            <button class="btn btn-sm btn-navy">Check</button>
                        </form>
                        @error('pincode') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Coupon --}}
                    <div class="fc-summary mb-3">
                        <h2 class="fc-summary__title">Coupon</h2>

                        @if ($cart->coupon && $totals->couponCode)
                            <div class="fc-coupon-applied">
                                <div>
                                    <strong>{{ $cart->coupon->code }}</strong>
                                    <small>{{ $cart->coupon->summary }}</small>
                                </div>
                                <form method="POST" action="{{ route('cart.coupon.remove') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger">Remove</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('cart.coupon.apply') }}" class="d-flex gap-2">
                                @csrf
                                <input type="text" name="code" class="form-control form-control-sm"
                                       placeholder="Coupon code" aria-label="Coupon code" maxlength="60">
                                <button class="btn btn-sm btn-navy">Apply</button>
                            </form>
                        @endif
                    </div>

                    @include('front.partials.order-summary')

                    <a href="{{ route('checkout.index') }}"
                       class="btn btn-gold btn-lg w-100 mt-3 {{ $canCheckout ? '' : 'disabled' }}">
                        Proceed to Checkout <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    @unless ($canCheckout)
                        <p class="small text-danger mt-2 mb-0">Fix the items above to continue.</p>
                    @endunless
                </div>
            </div>
        @endif
    </div>
</section>

@endsection
