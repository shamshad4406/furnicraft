@extends('front.layouts.app')

@section('title', 'Your Wishlist')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Your Wishlist</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Wishlist</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        @if ($products->isEmpty())
            <div class="fc-empty-state">
                <i class="bi bi-heart"></i>
                <h2>Nothing saved yet</h2>
                <p>Tap the heart on any product to keep it here for later.</p>
                <a href="{{ route('products') }}" class="btn btn-navy btn-sm">Browse products</a>
            </div>
        @else
            <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-4">
                @foreach ($products as $product)
                    <div class="col">
                        <div class="fc-wish-card">
                            @include('front.partials.product-card', ['product' => $product, 'showCategory' => true])

                            <div class="fc-wish-card__actions">
                                <form method="POST" action="{{ route('wishlist.move', $product) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-navy w-100">
                                        <i class="bi bi-cart-plus me-1"></i> Move to cart
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('wishlist.remove', $product) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-secondary w-100">Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@endsection
