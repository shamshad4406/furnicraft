{{-- Shared storefront product card: homepage, listings, related and recently viewed. --}}
<article class="fc-product-card">
    <div class="fc-product-card__media">
        <a href="{{ route('product.show', $product->slug) }}" aria-label="{{ $product->name }}">
            <img src="{{ $product->thumbnail_url ?? asset('images/logo.svg') }}"
                 alt="{{ $product->name }}" loading="lazy">
        </a>

        <button type="button" class="fc-product-card__wish" data-wishlist
                aria-pressed="false" aria-label="Add {{ $product->name }} to wishlist">
            <i class="bi bi-heart"></i>
        </button>

        @if ($product->discount_percent)
            <span class="fc-product-card__badge">−{{ $product->discount_percent }}%</span>
        @endif

        @if ($product->total_stock <= 0)
            <span class="fc-product-card__oos">Out of stock</span>
        @endif
    </div>

    <div class="fc-product-card__body">
        <h3 class="fc-product-card__name h6 mb-0">
            <a href="{{ route('product.show', $product->slug) }}">{{ $product->name }}</a>
        </h3>

        @if (($showCategory ?? false) && $product->category)
            <div class="fc-product-card__meta">{{ $product->category->name }}</div>
        @endif

        <div class="fc-product-card__price">
            {{ money($product->effective_price) }}
            @if ($product->sale_price)
                <s class="text-muted fw-normal" style="font-size:.82rem">{{ money($product->price) }}</s>
            @endif
        </div>

        {{-- The per-product enquiry form is built in Phase 6; the slug is
             carried across so it can be pre-filled then. --}}
        <a href="{{ route('page', ['slug' => 'contact-us', 'product' => $product->slug]) }}"
           class="btn btn-gold d-inline-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-envelope-fill"></i> Enquiry Now
        </a>
    </div>
</article>
