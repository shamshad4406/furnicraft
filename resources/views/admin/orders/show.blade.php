@extends('admin.layouts.app')

@section('title', 'Order '.$order->order_number)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">Orders</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $order->order_number }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">

        {{-- ------------------------------------------------- the order --}}
        <x-admin.card icon="bi-bag-check" title="{{ $order->order_number }}"
                      subtitle="Placed {{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $order->status_badge }}">{{ $order->status_label }}</span>
                <span class="badge text-bg-{{ $order->payment_badge }}">{{ $order->payment_label }}</span>
            </x-slot:actions>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ad-detail__label">Customer</div>
                    <div class="fw-semibold">{{ $order->customer_name }}</div>
                    <div class="small text-muted">{{ $order->customer_email }}</div>
                    <div class="small text-muted">{{ $order->customer_phone }}</div>
                    @if ($order->customer_id)
                        <span class="badge text-bg-light border mt-1">Registered</span>
                    @else
                        <span class="badge text-bg-light border mt-1">Guest checkout</span>
                    @endif
                    @if ($order->gst_number)
                        <div class="small text-muted mt-1">GSTIN {{ $order->gst_number }}</div>
                    @endif
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">Delivery address</div>
                    <address class="small mb-0">
                        {{ $order->shipping_address['name'] }}<br>
                        {{ collect([
                            $order->shipping_address['line1'] ?? null,
                            $order->shipping_address['line2'] ?? null,
                            $order->shipping_address['landmark'] ?? null,
                        ])->filter()->implode(', ') }}<br>
                        {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }}
                        — {{ $order->shipping_address['pincode'] }}<br>
                        {{ $order->shipping_address['phone'] }}
                    </address>
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">Payment</div>
                    <div class="small">{{ $order->payment_method === 'cod' ? 'Cash on delivery' : 'Online (Razorpay)' }}</div>
                    <div class="small text-muted">Paid {{ money($order->amount_paid) }}</div>
                    @if ($order->amount_due > 0)
                        <div class="small text-danger fw-semibold">Due {{ money($order->amount_due) }}</div>
                    @endif
                    @if ($order->delivery_days)
                        <div class="small text-muted mt-1">
                            Quoted {{ $order->delivery_days }} day(s) from confirmation
                        </div>
                    @endif
                </div>
            </div>

            @if ($order->cancellation_reason)
                <div class="alert alert-danger small mt-3 mb-0">
                    <strong>Cancelled.</strong> {{ $order->cancellation_reason }}
                    @if ($order->cancelledBy)
                        <span class="text-muted">— {{ $order->cancelledBy->name }}</span>
                    @else
                        <span class="text-muted">— by the customer</span>
                    @endif
                </div>
            @endif
        </x-admin.card>

        {{-- -------------------------------------------------- the items --}}
        <x-admin.card icon="bi-list-ul" title="Items" subtitle="{{ $order->items->count() }} line(s)" :flush="true" class="mt-3">
            <div class="table-responsive">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>HSN</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">GST</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    <div class="small fw-semibold">{{ $item->name }}</div>
                                    <div class="small text-muted">
                                        {{ $item->sku }}@if ($item->variant_label) · {{ $item->variant_label }}@endif
                                    </div>
                                </td>
                                <td class="small text-muted">{{ $item->hsn_code ?: '—' }}</td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end small">{{ money($item->unit_price) }}</td>
                                <td class="text-end small text-muted">
                                    {{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%
                                    <div>{{ money($item->tax_amount) }}</div>
                                </td>
                                <td class="text-end fw-semibold">{{ money($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end text-muted small">Subtotal</td>
                            <td class="text-end">{{ money($order->subtotal) }}</td>
                        </tr>
                        @if ($order->discount > 0)
                            <tr>
                                <td colspan="5" class="text-end text-muted small">
                                    Discount @if ($order->coupon_code) ({{ $order->coupon_code }}) @endif
                                </td>
                                <td class="text-end text-success">− {{ money($order->discount) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="5" class="text-end text-muted small">Delivery</td>
                            <td class="text-end">{{ $order->delivery_charge > 0 ? money($order->delivery_charge) : 'Free' }}</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-end fw-semibold">Total</td>
                            <td class="text-end fw-bold">{{ money($order->grand_total) }}</td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end small text-muted">
                                Includes {{ money($taxTotals['tax']) }} GST
                                ({{ $taxTotals['interstate'] ? 'IGST' : 'CGST + SGST' }})
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-admin.card>

        @include('admin.orders._shipments')
        @include('admin.orders._returns')

        {{-- ------------------------------------------------- the history --}}
        <x-admin.card icon="bi-clock-history" title="History" class="mt-3">
            <ol class="ad-timeline">
                @foreach ($order->statusHistories as $entry)
                    <li class="ad-timeline__item">
                        <div class="ad-timeline__dot"></div>
                        <div>
                            <div class="small">
                                <strong>{{ \App\Models\Order::STATUSES[$entry->to_status] ?? $entry->to_status }}</strong>
                                @if ($entry->from_status)
                                    <span class="text-muted">
                                        from {{ mb_strtolower(\App\Models\Order::STATUSES[$entry->from_status] ?? $entry->from_status) }}
                                    </span>
                                @endif
                                @if ($entry->customer_notified)
                                    <span class="badge text-bg-light border ms-1" title="The customer was emailed">
                                        <i class="bi bi-envelope-check"></i>
                                    </span>
                                @endif
                            </div>
                            @if ($entry->note)
                                <div class="small text-muted">{{ $entry->note }}</div>
                            @endif
                            <div class="text-muted" style="font-size:.72rem">
                                {{ $entry->created_at->format('d M Y, H:i') }} · {{ $entry->actor }}
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-admin.card>
    </div>

    {{-- ----------------------------------------------------- the actions --}}
    <div class="col-xl-4">
        @can('orders.edit')
            <x-admin.card icon="bi-arrow-right-circle" title="Move this order on">
                @if ($allowedNext === [])
                    <p class="small text-muted mb-0">
                        This order is {{ mb_strtolower($order->status_label) }}. Nothing more can be changed.
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                        @csrf @method('PATCH')

                        <div class="mb-2">
                            <label for="status" class="form-label">New status</label>
                            <select id="status" name="status" class="form-select form-select-sm" required>
                                @foreach ($allowedNext as $next)
                                    <option value="{{ $next }}">{{ $statuses[$next] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-2">
                            <label for="note" class="form-label">Note <span class="text-muted">(optional)</span></label>
                            <textarea id="note" name="note" rows="2" maxlength="500"
                                      class="form-control form-control-sm"
                                      placeholder="Why, or anything the customer should know"></textarea>
                            <div class="form-text" style="font-size:.72rem">
                                Shown to the customer when they are emailed, and kept in the history.
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="notify" value="1" id="notify" checked>
                            <label class="form-check-label small" for="notify">Email the customer</label>
                        </div>

                        <button class="btn btn-sm btn-primary w-100">Update status</button>
                    </form>

                    @if (in_array('cancelled', $allowedNext, true))
                        <p class="small text-muted mt-3 mb-0">
                            Cancelling releases the held stock and frees the coupon. It does not refund
                            anything — do that on the returns tab, or in Razorpay.
                        </p>
                    @endif
                @endif
            </x-admin.card>

            <x-admin.card icon="bi-receipt" title="Tax invoice" class="mt-3">
                @if ($order->hasInvoice())
                    <div class="fw-semibold">{{ $order->invoice_number }}</div>
                    <div class="small text-muted mb-2">Raised {{ $order->invoiced_at->format('d M Y, H:i') }}</div>
                    <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-printer"></i> Open / print
                    </a>
                @elseif ($canInvoice)
                    <p class="small text-muted">
                        Place of supply {{ $order->shipping_address['state'] ?? '—' }} —
                        {{ $taxTotals['interstate'] ? 'IGST' : 'CGST + SGST' }} applies.
                    </p>
                    <form method="POST" action="{{ route('admin.orders.invoice.raise', $order) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary w-100">Raise invoice</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">
                        The number is issued once and cannot be changed afterwards.
                    </p>
                @else
                    <p class="small text-muted mb-0">
                        @if ($order->status === 'cancelled')
                            A cancelled order is never invoiced.
                        @else
                            Confirm the order first; an invoice cannot be raised while it is pending.
                        @endif
                    </p>
                @endif
            </x-admin.card>

            <x-admin.card icon="bi-sticky" title="Internal notes" class="mt-3">
                <form method="POST" action="{{ route('admin.orders.notes', $order) }}">
                    @csrf @method('PATCH')
                    <textarea name="notes" rows="4" maxlength="500"
                              class="form-control form-control-sm mb-2"
                              placeholder="Not shown to the customer">{{ old('notes', $order->notes) }}</textarea>
                    <button class="btn btn-sm btn-outline-secondary w-100">Save notes</button>
                </form>
            </x-admin.card>
        @endcan

        @if ($order->payments->isNotEmpty())
            <x-admin.card icon="bi-credit-card" title="Payments" class="mt-3" :flush="true">
                <div class="table-responsive">
                    <table class="ad-table">
                        <tbody>
                            @foreach ($order->payments as $payment)
                                <tr>
                                    <td>
                                        <div class="small fw-semibold">{{ money($payment->amount) }}</div>
                                        <div class="text-muted" style="font-size:.72rem">
                                            {{ $payment->gateway }}@if ($payment->method) · {{ $payment->method }} @endif
                                        </div>
                                        @if ($payment->gateway_payment_id)
                                            <div class="text-muted" style="font-size:.7rem">
                                                <code>{{ $payment->gateway_payment_id }}</code>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <span class="badge text-bg-{{ $payment->status === 'captured' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                        <div class="text-muted" style="font-size:.7rem">
                                            {{ ($payment->paid_at ?? $payment->created_at)->format('d M, H:i') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        @endif
    </div>
</div>

@endsection
