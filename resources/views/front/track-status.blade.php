@extends('front.layouts.app')

@section('title', 'Order '.$order->order_number)
@section('robots', 'noindex, nofollow')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Order {{ $order->order_number }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('track.form') }}">Track order</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $order->order_number }}</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="fc-panel mb-3">
                    <h2 class="fc-panel__title">Where it has got to</h2>

                    <ol class="fc-timeline">
                        @foreach ($steps as $step)
                            <li class="fc-timeline__step is-{{ $step['state'] }}">
                                <span class="fc-timeline__dot" aria-hidden="true">
                                    @if ($step['state'] === 'done')
                                        <i class="bi bi-check"></i>
                                    @elseif ($step['state'] === 'stopped')
                                        <i class="bi bi-x"></i>
                                    @endif
                                </span>
                                <span class="fc-timeline__label">{{ $step['label'] }}</span>
                            </li>
                        @endforeach
                    </ol>

                    @if ($order->status === 'delivered')
                        <p class="small text-muted mt-3 mb-0">
                            Delivered. We hope you are enjoying it — any problem, just reply to your
                            confirmation email.
                        </p>
                    @elseif (in_array($order->status, ['cancelled', 'returned'], true))
                        <p class="small text-muted mt-3 mb-0">
                            This order is {{ Str::lower(\App\Models\Order::STATUSES[$order->status]) }}.
                            @if ($order->payment_status === 'paid')
                                Any amount paid is refunded to the original payment method.
                            @endif
                        </p>
                    @elseif ($order->delivery_days)
                        <p class="small text-muted mt-3 mb-0">
                            Expected by
                            <strong>{{ ($order->confirmed_at ?? $order->placed_at ?? $order->created_at)->addDays($order->delivery_days)->format('D, d M Y') }}</strong>.
                            Our team calls to confirm a delivery slot.
                        </p>
                    @else
                        <p class="small text-muted mt-3 mb-0">
                            Our team will call you shortly to confirm the delivery date.
                        </p>
                    @endif
                </div>

                <div class="fc-panel">
                    <h2 class="fc-panel__title">What you ordered</h2>

                    @foreach ($order->items as $item)
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <div>
                                <strong class="d-block small">{{ $item->name }}</strong>
                                <span class="small text-muted">
                                    @if ($item->variant_label) {{ $item->variant_label }} · @endif
                                    Qty {{ $item->quantity }}
                                </span>
                            </div>
                            <strong class="small text-nowrap">{{ money($item->line_total) }}</strong>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-between pt-3">
                        <span class="text-muted">Order total</span>
                        <strong>{{ money($order->grand_total) }}</strong>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="fc-panel mb-3">
                    <h2 class="fc-panel__title">Payment</h2>

                    <dl class="fc-summary__rows mb-0">
                        <dt>Status</dt>
                        <dd>
                            <span class="badge text-bg-{{ $order->payment_badge }}">
                                {{ \App\Models\Order::PAYMENT_STATUSES[$order->payment_status] }}
                            </span>
                        </dd>

                        <dt>Method</dt>
                        <dd>{{ $order->payment_method === 'cod' ? 'Cash on delivery' : 'Online' }}</dd>

                        <dt>Paid</dt>
                        <dd>{{ money($order->amount_paid) }}</dd>

                        @if ($order->amount_due > 0)
                            <dt>Due</dt>
                            <dd class="fw-semibold">{{ money($order->amount_due) }}</dd>
                        @endif

                        <dt>Placed</dt>
                        <dd>{{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}</dd>
                    </dl>
                </div>

                <div class="fc-panel">
                    <h2 class="fc-panel__title">Delivering to</h2>

                    {{-- City and pincode only. A tracking link can be forwarded or
                         left behind in a shared browser; the full address and phone
                         number stay behind the account or the original session. --}}
                    <p class="small mb-2">
                        {{ $order->shipping_address['city'] ?? '' }},
                        {{ $order->shipping_address['state'] ?? '' }}
                        — {{ $order->shipping_address['pincode'] ?? $order->shipping_pincode }}
                    </p>

                    @auth('customer')
                        @if ($order->customer_id === auth('customer')->id())
                            <a href="{{ route('account.order', $order->public_token) }}" class="btn btn-outline-secondary btn-sm w-100">
                                Full order details
                            </a>
                        @endif
                    @else
                        <p class="small text-muted mb-0">
                            The full delivery address is on your confirmation email.
                        </p>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
