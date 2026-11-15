@extends('front.layouts.app')

@section('title', 'Order '.$order->order_number)

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Order {{ $order->order_number }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('account.orders') }}">My Orders</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $order->order_number }}</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-3">@include('front.account._nav')</div>

            <div class="col-lg-9">
                <div class="fc-panel mb-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h2 class="fc-panel__title mb-2">Status</h2>
                            <span class="badge text-bg-{{ $order->status_badge }}">
                                {{ \App\Models\Order::STATUSES[$order->status] }}
                            </span>
                            <span class="badge text-bg-{{ $order->payment_badge }}">
                                {{ \App\Models\Order::PAYMENT_STATUSES[$order->payment_status] }}
                            </span>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Placed</div>
                            <strong>{{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}</strong>
                        </div>
                    </div>

                    @if ($order->payment_status === 'pending' && $order->status === 'pending')
                        <div class="alert alert-warning small mt-3 mb-0 d-flex justify-content-between align-items-center gap-2">
                            <span>This order has not been paid yet.</span>
                            <a href="{{ route('checkout.pay', $order) }}" class="btn btn-sm btn-gold">Pay now</a>
                        </div>
                    @endif

                    @if ($order->amount_due > 0 && $order->payment_method === 'cod')
                        <p class="small text-muted mt-3 mb-0">
                            <i class="bi bi-cash-coin me-1"></i>
                            {{ money($order->amount_due) }} payable on delivery.
                        </p>
                    @endif

                    @if ($order->shipments->isNotEmpty())
                        <hr>
                        <div class="ad-detail__label">On its way</div>
                        @foreach ($order->shipments as $shipment)
                            <div class="small mb-1">
                                <strong>{{ $shipment->consignment_number }}</strong>
                                <span class="badge text-bg-{{ $shipment->status_badge }} ms-1">
                                    {{ \App\Models\Shipment::STATUSES[$shipment->status] }}
                                </span>
                                @if ($shipment->courier)
                                    <div class="text-muted">
                                        {{ $shipment->courier }}@if ($shipment->tracking_number) · {{ $shipment->tracking_number }}@endif
                                    </div>
                                @endif
                                @if ($shipment->expected_delivery_on && ! $shipment->delivered_at)
                                    <div class="text-muted">Expected {{ $shipment->expected_delivery_on->format('D, d M Y') }}</div>
                                @endif
                                @if ($shipment->tracking_url)
                                    <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">
                                        Track with the courier <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    {{-- What the customer can actually do from here. --}}
                    <hr>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <a href="{{ route('track.status', $order->public_token) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-truck"></i> See its progress
                        </a>

                        @if ($order->isReturnable())
                            <a href="{{ route('account.order.return', $order->public_token) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-return-left"></i> Return an item
                            </a>
                        @endif

                        @if ($order->isCancellable())
                            <button class="btn btn-sm btn-outline-danger" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#cancelOrder"
                                    aria-expanded="false" aria-controls="cancelOrder">
                                Cancel this order
                            </button>
                        @endif
                    </div>

                    @if ($order->returns->isNotEmpty())
                        <div class="small text-muted mt-2">
                            @foreach ($order->returns as $claim)
                                <div>
                                    Return <strong>{{ $claim->reference }}</strong> —
                                    {{ \App\Models\OrderReturn::STATUSES[$claim->status] }}
                                    @if ($claim->customer_message)
                                        <div><em>{{ $claim->customer_message }}</em></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($order->isCancellable())
                        <div class="collapse mt-3" id="cancelOrder">
                            <form method="POST" action="{{ route('account.order.cancel', $order->public_token) }}">
                                @csrf @method('PATCH')

                                <label for="reason" class="form-label">Why are you cancelling?</label>
                                <input type="text" id="reason" name="reason" maxlength="300" required
                                       class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                       value="{{ old('reason') }}">
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                <p class="small text-muted mt-2">
                                    @if ($order->amount_paid > 0)
                                        {{ money($order->amount_paid) }} will be refunded to the way you paid,
                                        usually within 5 to 7 working days.
                                    @else
                                        Nothing has been charged, so there is nothing to refund.
                                    @endif
                                </p>

                                <button class="btn btn-sm btn-outline-danger">Yes, cancel the order</button>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="fc-panel mb-3">
                    <h2 class="fc-panel__title">Items</h2>

                    @foreach ($order->items as $item)
                        <div class="fc-confirm__item">
                            <div>
                                <strong>{{ $item->name }}</strong>
                                <small>
                                    {{ $item->variant_label ? $item->variant_label.' · ' : '' }}
                                    SKU {{ $item->sku }} · Qty {{ $item->quantity }} × {{ money($item->unit_price) }}
                                </small>
                            </div>
                            <span>{{ money($item->line_total) }}</span>
                        </div>
                    @endforeach

                    <dl class="fc-summary__rows mt-3">
                        <dt>Subtotal</dt>
                        <dd>{{ money($order->subtotal) }}</dd>

                        @if ($order->discount > 0)
                            <dt class="is-discount">Discount {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</dt>
                            <dd class="is-discount">− {{ money($order->discount) }}</dd>
                        @endif

                        <dt>Delivery</dt>
                        <dd>{{ $order->delivery_charge > 0 ? money($order->delivery_charge) : 'Free' }}</dd>
                    </dl>

                    <div class="fc-summary__total">
                        <span>Total</span>
                        <strong>{{ money($order->grand_total) }}</strong>
                    </div>

                    @if ($order->tax_total > 0)
                        <p class="fc-summary__tax">Includes {{ money($order->tax_total) }} GST</p>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fc-panel h-100">
                            <h2 class="fc-panel__title">Delivery address</h2>
                            <p class="small mb-0">
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
                    </div>

                    <div class="col-md-6">
                        <div class="fc-panel h-100">
                            <h2 class="fc-panel__title">Payment</h2>
                            <dl class="fc-summary__rows mb-0">
                                <dt>Method</dt>
                                <dd>{{ $order->payment_method === 'cod' ? 'Cash on delivery' : 'Online' }}</dd>
                                <dt>Paid</dt>
                                <dd>{{ money($order->amount_paid) }}</dd>
                                <dt>Due</dt>
                                <dd>{{ money($order->amount_due) }}</dd>
                                @if ($order->gst_number)
                                    <dt>GST number</dt>
                                    <dd>{{ $order->gst_number }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
