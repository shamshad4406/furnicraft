<a href="{{ route('account.order', $order->public_token) }}" class="fc-order-row">
    <div class="fc-order-row__main">
        <strong>{{ $order->order_number }}</strong>
        <small>
            {{ $order->placed_at?->format('d M Y') ?? $order->created_at->format('d M Y') }}
            · {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}
        </small>
    </div>

    <div class="fc-order-row__status">
        <span class="badge text-bg-{{ $order->status_badge }}">
            {{ \App\Models\Order::STATUSES[$order->status] }}
        </span>
        <span class="badge text-bg-{{ $order->payment_badge }}">
            {{ \App\Models\Order::PAYMENT_STATUSES[$order->payment_status] }}
        </span>
    </div>

    <div class="fc-order-row__total">
        <strong>{{ money($order->grand_total) }}</strong>
        <i class="bi bi-chevron-right"></i>
    </div>
</a>
