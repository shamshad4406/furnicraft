@extends('front.layouts.app')

@section('title', $heading)
@section('robots', 'noindex, nofollow')

@section('content')

<section class="fc-section">
    <div class="container-fc">
        <div class="fc-confirm">
            <div class="fc-confirm__badge is-{{ $tone }}">
                <i class="bi {{ $tone === 'success' ? 'bi-check-lg' : ($tone === 'warning' ? 'bi-exclamation-lg' : 'bi-envelope-slash') }}"></i>
            </div>

            <h1>{{ $heading }}</h1>

            <p class="text-muted">{{ $message }}</p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                <a href="{{ route('products') }}" class="btn btn-navy">Browse the shop</a>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">Back home</a>
            </div>
        </div>
    </div>
</section>

@endsection
