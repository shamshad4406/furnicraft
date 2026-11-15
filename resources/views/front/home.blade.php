@extends('front.layouts.app')

@section('title', settings('company_name', 'FurniCraft'))

@push('schema')
    @include('front.partials.schema', ['data' => app(\App\Services\SeoService::class)->organisation()])
@endpush

@section('content')

    {{-- ------------------------------------------------------------ hero --}}
    <section class="fc-hero">
        <div id="fcHero" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="6000">
            <div class="carousel-inner">
                @foreach ($slides as $index => $slide)
                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                        {{-- A portrait crop on phones where one was given;
                             the wide image is squeezed otherwise. --}}
                        <picture>
                            @if ($slide['mobile_image'] && $slide['mobile_image'] !== $slide['image'])
                                <source media="(max-width: 575px)" srcset="{{ $slide['mobile_image'] }}">
                            @endif
                            <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] }}"
                                 @if ($index > 0) loading="lazy" @endif>
                        </picture>

                        <div class="fc-hero__caption">
                            <div class="container-fc">
                                <div class="fc-hero__text">
                                    <h1>
                                        {{ $slide['title'] }}
                                        @if ($slide['highlight'])
                                            <span>{{ $slide['highlight'] }}</span>
                                        @endif
                                    </h1>
                                    @if ($slide['subtitle'])
                                        <p>{{ $slide['subtitle'] }}</p>
                                    @endif
                                    @if ($slide['cta'])
                                        <a href="{{ $slide['url'] }}" class="btn btn-navy btn-lg d-inline-flex align-items-center gap-2">
                                            {{ $slide['cta'] }} <i class="bi bi-arrow-right"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#fcHero" data-bs-slide="prev">
                <span class="fc-hero__arrow" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#fcHero" data-bs-slide="next">
                <span class="fc-hero__arrow" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                <span class="visually-hidden">Next</span>
            </button>

            <div class="carousel-indicators">
                @foreach ($slides as $index => $slide)
                    <button type="button" data-bs-target="#fcHero" data-bs-slide-to="{{ $index }}"
                            class="{{ $index === 0 ? 'active' : '' }}"
                            aria-label="Slide {{ $index + 1 }}" @if($index === 0) aria-current="true" @endif></button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------ categories --}}
    <section class="fc-section">
        <div class="container-fc">
            <div class="fc-section-title">
                <h2>Our Categories</h2>
            </div>

            <div class="row g-3 row-cols-2 row-cols-sm-3 row-cols-lg-6">
                @foreach ($categories as $category)
                    <div class="col">
                        <a href="{{ route('category.show', $category->slug) }}" class="fc-category-card">
                            <img src="{{ $category->image_url ?? asset('images/logo.svg') }}"
                                 alt="{{ $category->name }}" loading="lazy">
                            <span>{{ $category->name }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------- featured products --}}
    <section class="fc-section pt-0">
        <div class="container-fc">
            <div class="fc-section-head">
                <div class="fc-section-title mb-0">
                    <h2>Featured Products</h2>
                </div>
                <a href="{{ route('products') }}"
                   class="fc-section-head__action btn btn-navy btn-sm d-inline-flex align-items-center gap-2">
                    View All Products <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5">
                @foreach ($featured as $product)
                    <div class="col">
                        @include('front.partials.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ------------------------------------------ best sellers / arrivals --}}
    @foreach ([
        ['title' => 'Best Sellers', 'items' => $bestSellers, 'filter' => 'best_seller'],
        ['title' => 'New Arrivals', 'items' => $newArrivals, 'filter' => 'new_arrival'],
        ['title' => 'Offers', 'items' => $offers, 'filter' => 'discount'],
    ] as $strip)
        @if ($strip['items']->isNotEmpty())
            <section class="fc-section pt-0">
                <div class="container-fc">
                    <div class="fc-section-head">
                        <div class="fc-section-title mb-0"><h2>{{ $strip['title'] }}</h2></div>
                        <a href="{{ route('products', [$strip['filter'] => 1]) }}"
                           class="fc-section-head__action btn btn-navy btn-sm d-inline-flex align-items-center gap-2">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5">
                        @foreach ($strip['items'] as $product)
                            <div class="col">
                                @include('front.partials.product-card', ['product' => $product])
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endforeach

    {{-- ------------------------------------------------- recently viewed --}}
    @if ($recentlyViewed->isNotEmpty())
        <section class="fc-section pt-0">
            <div class="container-fc">
                <div class="fc-section-title"><h2>Recently Viewed</h2></div>
                <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5">
                    @foreach ($recentlyViewed->take(5) as $product)
                        <div class="col">
                            @include('front.partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- --------------------------------------------------- testimonials --}}
    <section class="fc-testimonials">
        <div class="container-fc">
            <div class="fc-section-title"><h2>What Our Customers Say</h2></div>

            <div class="row g-3 row-cols-1 row-cols-md-3">
                {{-- Real approved reviews once staff have featured some;
                     the written-in ones until then. --}}
                @forelse ($reviews as $review)
                    <div class="col">
                        <figure class="fc-testimonial">
                            <div class="fc-stars" role="img" aria-label="{{ $review->stars }} out of 5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star{{ $i <= $review->stars ? '-fill' : '' }}"></i>
                                @endfor
                            </div>
                            <blockquote>{{ Str::limit($review->body, 220) }}</blockquote>
                            <figcaption>
                                <strong>{{ $review->display_name }}</strong>
                                <span>
                                    {{ $review->city }}@if ($review->city && $review->product) · @endif
                                    @if ($review->product){{ $review->product->name }}@endif
                                </span>
                                @if ($review->is_verified)
                                    <span class="fc-verified"><i class="bi bi-patch-check-fill"></i> Verified purchase</span>
                                @endif
                            </figcaption>
                        </figure>
                    </div>
                @empty
                    @foreach ($testimonials as $testimonial)
                        <div class="col">
                            <figure class="fc-testimonial">
                                <i class="bi bi-quote"></i>
                                <blockquote>{{ $testimonial['quote'] }}</blockquote>
                                <figcaption>
                                    <strong>{{ $testimonial['name'] }}</strong>
                                    <span>{{ $testimonial['place'] }}</span>
                                </figcaption>
                            </figure>
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------------------- USPs --}}
    <section class="fc-usp">
        <div class="container-fc">
            <div class="row g-0 row-cols-2 row-cols-lg-4">
                @foreach ($usps as $usp)
                    <div class="col">
                        <div class="fc-usp__item">
                            <i class="bi {{ $usp['icon'] }}"></i>
                            <div>
                                <strong>{{ $usp['title'] }}</strong>
                                @if ($usp['subtitle'])
                                    <small>{{ $usp['subtitle'] }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

@endsection
