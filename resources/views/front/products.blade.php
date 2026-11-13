@extends('front.layouts.app')

@section('title', request('q') ? 'Search: '.request('q') : 'All Products')

@section('content')

    <section class="fc-page-hero">
        <div class="container-fc">
            <h1>{{ request('q') ? 'Search results' : 'All Products' }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ request('q') ? '“'.request('q').'”' : 'Products' }}
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="fc-section">
        <div class="container-fc">
            <div class="row g-4">
                <div class="col-lg-3">
                    @include('front.partials.filters', ['action' => route('products')])
                </div>

                <div class="col-lg-9">
                    @include('front.partials.listing-toolbar')

                    @if ($products->isEmpty())
                        @include('front.partials.listing-empty')
                    @else
                        <div class="row g-3 row-cols-2 row-cols-md-3">
                            @foreach ($products as $product)
                                <div class="col">
                                    @include('front.partials.product-card', ['product' => $product, 'showCategory' => true])
                                </div>
                            @endforeach
                        </div>

                        @if ($products->hasPages())
                            <div class="fc-pagination">{{ $products->links('pagination::bootstrap-5') }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </section>

@endsection
