@extends('front.layouts.app')

@section('title', 'Payment not completed')

@section('content')

<section class="fc-section">
    <div class="container-fc">
        <div class="fc-confirm is-failed">
            <div class="fc-confirm__badge is-failed"><i class="bi bi-x-lg"></i></div>

            <h1>Payment not completed</h1>
            <p class="fc-confirm__lead">
                Order <strong>{{ $order->order_number }}</strong> has not been paid, so nothing has been charged.
            </p>

            @if ($canRetry)
                <p class="text-muted">
                    We are holding your items for a short while. You can try again, or choose cash on
                    delivery if that is easier.
                </p>

                <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                    <a href="{{ route('checkout.pay', $order) }}" class="btn btn-gold">
                        <i class="bi bi-arrow-repeat me-1"></i> Try payment again
                    </a>
                    <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary">Back to cart</a>
                </div>
            @else
                <p class="text-muted">This order can no longer be paid. Please place a new one.</p>
                <a href="{{ route('cart.index') }}" class="btn btn-navy mt-3">Back to cart</a>
            @endif

            <p class="fc-confirm__help">
                Money taken but no confirmation? Email
                <a href="mailto:{{ settings('company_email') }}">{{ settings('company_email') }}</a>
                with the order number and we will sort it out.
            </p>
        </div>
    </div>
</section>

@endsection
