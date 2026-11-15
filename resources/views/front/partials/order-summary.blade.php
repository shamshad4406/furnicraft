{{-- Money panel shared by the cart, the checkout and the payment page. --}}
<div class="fc-summary">
    <h2 class="fc-summary__title">Order summary</h2>

    <dl class="fc-summary__rows">
        <dt>Subtotal</dt>
        <dd>{{ money($totals->subtotal) }}</dd>

        @if ($totals->discount > 0)
            <dt class="is-discount">
                Discount
                @if ($totals->couponCode)
                    <span class="fc-summary__coupon">{{ $totals->couponCode }}</span>
                @endif
            </dt>
            <dd class="is-discount">− {{ money($totals->discount) }}</dd>
        @endif

        <dt>
            Delivery
            @if ($totals->deliveryDays)
                <small>in {{ $totals->deliveryDays }} {{ Str::plural('day', $totals->deliveryDays) }}</small>
            @endif
        </dt>
        <dd>
            @if ($totals->deliveryCharge > 0)
                {{ money($totals->deliveryCharge) }}
            @elseif ($totals->freeDelivery)
                <span class="text-success">Free</span>
            @elseif ($pincode ?? null)
                <span class="text-success">Free</span>
            @else
                <small class="text-muted">Enter a pincode</small>
            @endif
        </dd>
    </dl>

    <div class="fc-summary__total">
        <span>Total</span>
        <strong>{{ money($totals->grandTotal) }}</strong>
    </div>

    @if ($totals->taxTotal > 0)
        <p class="fc-summary__tax">Includes {{ money($totals->taxTotal) }} GST</p>
    @endif

    @foreach ($totals->notices as $notice)
        <p class="fc-summary__notice"><i class="bi bi-info-circle"></i> {{ $notice }}</p>
    @endforeach

    {{ $slot ?? '' }}
</div>
