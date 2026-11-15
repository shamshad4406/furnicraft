@extends('front.layouts.app')

@section('title', 'Payment')

@section('content')

<section class="fc-section">
    <div class="container-fc">
        <div class="fc-pay-card"
             data-pay
             data-create-url="{{ route('payment.create', $order) }}"
             data-callback-url="{{ route('payment.callback', $order) }}"
             data-cancel-url="{{ route('payment.cancelled', $order) }}"
             data-theme="#16263f">

            <div class="fc-pay-card__head">
                <i class="bi bi-shield-lock"></i>
                <div>
                    <h1>Complete your payment</h1>
                    <p>Order <strong>{{ $order->order_number }}</strong></p>
                </div>
            </div>

            @if ($testMode)
                <div class="alert alert-warning small">
                    <i class="bi bi-cone-striped me-1"></i>
                    <strong>Test mode.</strong> Use Razorpay's test card <code>4111 1111 1111 1111</code>,
                    any future expiry and any CVV. No real money moves.
                </div>
            @endif

            <dl class="fc-pay-card__rows">
                <dt>Order total</dt>
                <dd>{{ money($order->grand_total) }}</dd>

                @if ($isAdvance)
                    <dt>Advance payable now</dt>
                    <dd>{{ money($payable) }}</dd>
                    <dt>On delivery</dt>
                    <dd>{{ money($order->grand_total - $payable) }}</dd>
                @endif
            </dl>

            <div class="fc-pay-card__total">
                <span>Paying now</span>
                <strong>{{ money($payable) }}</strong>
            </div>

            <button type="button" class="btn btn-gold btn-lg w-100 mt-3" data-pay-button>
                <i class="bi bi-lock-fill me-1"></i> Pay {{ money($payable) }}
            </button>

            <p class="fc-pay-card__note" data-pay-status role="status" aria-live="polite"></p>

            <p class="fc-pay-card__small">
                Do not refresh or close this page while the payment window is open.
                If something goes wrong your order is kept for 30 minutes so you can try again.
            </p>
        </div>
    </div>
</section>

@endsection

@push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endpush
