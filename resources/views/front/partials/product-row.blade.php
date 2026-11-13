@if ($items->isNotEmpty())
    <div class="fc-product-row">
        <div class="fc-section-title"><h2>{{ $title }}</h2></div>

        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-4">
            @foreach ($items as $product)
                <div class="col">
                    @include('front.partials.product-card', ['product' => $product, 'showCategory' => true])
                </div>
            @endforeach
        </div>
    </div>
@endif
