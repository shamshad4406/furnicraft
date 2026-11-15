@extends('front.layouts.app')

@section('title', 'Order confirmed')

@section('content')

<section class="fc-section">
    <div class="container-fc">
        <div class="fc-confirm">
            <div class="fc-confirm__badge"><i class="bi bi-check-lg"></i></div>

            <h1>Thank you, {{ Str::before($order->customer_name, ' ') }}!</h1>
            <p class="fc-confirm__lead">
                Your order <strong>{{ $order->order_number }}</strong> has been placed.
                A confirmation has been sent to {{ $order->customer_email }}.
            </p>

            <div class="fc-confirm__facts">
                <div>
                    <span>Order number</span>
                    <strong>{{ $order->order_number }}</strong>
                </div>
                <div>
                    <span>Payment</span>
                    <strong>
                        {{ $order->payment_method === 'cod' ? 'Cash on delivery' : 'Paid online' }}
                        <span class="badge text-bg-{{ $order->payment_badge }}">
                            {{ \App\Models\Order::PAYMENT_STATUSES[$order->payment_status] }}
                        </span>
                    </strong>
                </div>
                @if ($order->delivery_days)
                    <div>
                        <span>Expected delivery</span>
                        <strong>{{ now()->addDays($order->delivery_days)->format('D, d M Y') }}</strong>
                    </div>
                @endif
                <div>
                    <span>Total</span>
                    <strong>{{ money($order->grand_total) }}</strong>
                </div>
            </div>

            @if ($order->amount_due > 0)
                <div class="alert alert-warning small mt-3">
                    <i class="bi bi-cash-coin me-1"></i>
                    <strong>{{ money($order->amount_due) }}</strong> is payable on delivery.
                </div>
            @endif

            <div class="fc-confirm__items">
                <h2>What you ordered</h2>
                @foreach ($order->items as $item)
                    <div class="fc-confirm__item">
                        <div>
                            <strong>{{ $item->name }}</strong>
                            <small>
                                {{ $item->variant_label ? $item->variant_label.' · ' : '' }}
                                SKU {{ $item->sku }} · Qty {{ $item->quantity }}
                            </small>
                        </div>
                        <span>{{ money($item->line_total) }}</span>
                    </div>
                @endforeach

                <div class="fc-confirm__item is-total">
                    <div><strong>Total paid</strong></div>
                    <span>{{ money($order->amount_paid) }}</span>
                </div>
            </div>

            <div class="fc-confirm__address">
                <h2>Delivering to</h2>
                <p>
                    {{ $order->shipping_address['name'] }}<br>
                    {{ collect([
                        $order->shipping_address['line1'] ?? null,
                        $order->shipping_address['line2'] ?? null,
                        $order->shipping_address['landmark'] ?? null,
                    ])->filter()->implode(', ') }}<br>
                    {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }}
                    — {{ $order->shipping_address['pincode'] }}<br>
                    {{ $order->shipping_address['phone'] }}
                </p>
            </div>

            {{-- Say where to come back to before they leave this page: for a
                 guest this is the only moment we have their attention, and
                 their session is the only thing holding this order open. --}}
            <div class="alert alert-light border text-start small">
                <strong><i class="bi bi-box-seam"></i> Checking on this order later</strong>
                @auth('customer')
                    <p class="mb-0 mt-1">
                        It is in <a href="{{ route('account.orders') }}">My Orders</a>, with its
                        current status and payment details.
                    </p>
                @else
                    <p class="mb-0 mt-1">
                        Use <a href="{{ route('track.form') }}">Track your order</a> with order number
                        <strong>{{ $order->order_number }}</strong> and the email address you ordered
                        with. The same link is in your confirmation email.
                    </p>
                @endauth
            </div>

            <p class="text-muted small">
                Invoices and cancellation arrive with the orders module in Phase 5.
                Until then we will call you to confirm the delivery slot.
            </p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                <a href="{{ route('track.status', $order->public_token) }}" class="btn btn-navy">
                    <i class="bi bi-truck"></i> Track this order
                </a>
                <a href="{{ route('products') }}" class="btn btn-outline-secondary">Continue shopping</a>
                <a href="{{ route('page', 'contact-us') }}" class="btn btn-outline-secondary">Contact us</a>
            </div>
        </div>
    </div>
</section>

@endsection
