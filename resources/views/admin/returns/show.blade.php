@extends('admin.layouts.app')

@section('title', 'Return '.$return->reference)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.returns.index') }}">Returns</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $return->reference }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-7">
        <x-admin.card icon="bi-arrow-return-left" title="{{ $return->reference }}"
                      subtitle="Raised {{ $return->requested_at->format('d M Y, H:i') }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $return->status_badge }}">{{ $statuses[$return->status] }}</span>
                <span class="badge text-bg-light border">{{ \App\Models\OrderReturn::TYPES[$return->type] }}</span>
            </x-slot:actions>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ad-detail__label">Order</div>
                    <a href="{{ route('admin.orders.show', $return->order) }}">{{ $return->order->order_number }}</a>
                    <div class="small text-muted">{{ $return->order->customer_name }}</div>
                    <div class="small text-muted">{{ $return->order->customer_email }}</div>
                    <div class="small text-muted">{{ $return->order->customer_phone }}</div>
                </div>

                <div class="col-md-6">
                    <div class="ad-detail__label">Reason</div>
                    <div>{{ $return->reason_label }}</div>
                    @if ($return->details)
                        <div class="small text-muted mt-1">“{{ $return->details }}”</div>
                    @endif
                </div>
            </div>

            @if ($return->customer_message)
                <div class="alert alert-light border small mt-3 mb-0">
                    <strong>Told to the customer:</strong> {{ $return->customer_message }}
                </div>
            @endif

            @if ($return->status === 'refunded')
                <div class="alert alert-success small mt-3 mb-0">
                    Refunded {{ money($return->refund_amount) }}
                    @if ($return->refund_method)
                        — {{ ['original' => 'to the original payment method', 'bank' => 'by bank transfer', 'store_credit' => 'as store credit'][$return->refund_method] ?? $return->refund_method }}
                    @endif
                    @if ($return->refund_reference)
                        <div class="text-muted">Reference {{ $return->refund_reference }}</div>
                    @endif
                </div>
            @endif
        </x-admin.card>

        <x-admin.card icon="bi-box" title="What is coming back" class="mt-3" :flush="true">
            <div class="table-responsive">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Refund value</th>
                            <th class="text-end">Back in stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($return->items as $line)
                            <tr>
                                <td class="small">
                                    {{ $line->orderItem?->name ?? 'Item' }}
                                    <div class="text-muted">{{ $line->orderItem?->sku }}</div>
                                </td>
                                <td class="text-end">{{ $line->quantity }}</td>
                                <td class="text-end small">{{ money($line->refund_amount) }}</td>
                                <td class="text-end small">
                                    @if (in_array($return->status, ['received', 'refunded', 'replaced', 'closed'], true))
                                        {!! $line->restock
                                            ? '<span class="text-success">Yes</span>'
                                            : '<span class="text-muted">No</span>' !!}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    </div>

    {{-- ------------------------------------------------------- handling --}}
    <div class="col-xl-5">
        @can('orders.edit')
            @if ($return->status === 'requested')
                <x-admin.card icon="bi-check2-circle" title="Decide">
                    <form method="POST" action="{{ route('admin.returns.approve', $return) }}" class="mb-3">
                        @csrf @method('PATCH')

                        <label for="approve_refund" class="form-label">Refund to offer</label>
                        <input type="number" step="0.01" min="0" id="approve_refund" name="refund_amount"
                               value="{{ old('refund_amount', $return->refund_amount) }}"
                               class="form-control form-control-sm mb-2">
                        <div class="form-text mb-2" style="font-size:.72rem">
                            Worked out from what was actually paid for these units, discounts included.
                        </div>

                        <label for="approve_message" class="form-label">Message to the customer</label>
                        <textarea id="approve_message" name="customer_message" rows="2" maxlength="500"
                                  class="form-control form-control-sm mb-2"
                                  placeholder="When we will collect, what happens next"></textarea>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="notify" value="1" id="approve_notify" checked>
                            <label class="form-check-label small" for="approve_notify">Email the customer</label>
                        </div>

                        <button class="btn btn-sm btn-primary w-100">Approve</button>
                    </form>

                    <hr>

                    <form method="POST" action="{{ route('admin.returns.reject', $return) }}">
                        @csrf @method('PATCH')

                        <label for="reject_message" class="form-label">
                            Why it is being refused <span class="text-danger">*</span>
                        </label>
                        <textarea id="reject_message" name="customer_message" rows="2" maxlength="500" required
                                  class="form-control form-control-sm @error('customer_message') is-invalid @enderror"
                                  placeholder="The customer is told this, so make it something they can act on">{{ old('customer_message') }}</textarea>
                        @error('customer_message') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        <div class="form-check my-2">
                            <input class="form-check-input" type="checkbox" name="notify" value="1" id="reject_notify" checked>
                            <label class="form-check-label small" for="reject_notify">Email the customer</label>
                        </div>

                        <button class="btn btn-sm btn-outline-danger w-100">Refuse</button>
                    </form>
                </x-admin.card>
            @endif

            @if ($return->status === 'approved')
                <x-admin.card icon="bi-truck" title="Collected from the customer" class="mt-3">
                    <p class="small text-muted">
                        Use this when the item has been picked up but is not back with you yet.
                    </p>

                    <form method="POST" action="{{ route('admin.returns.pickup', $return) }}">
                        @csrf @method('PATCH')

                        <label for="pickup_note" class="form-label">Note <span class="text-muted">(internal)</span></label>
                        <input type="text" id="pickup_note" name="staff_note" maxlength="500"
                               class="form-control form-control-sm mb-2" placeholder="Who collected it, when">

                        <button class="btn btn-sm btn-outline-primary w-100">Mark collected</button>
                    </form>
                </x-admin.card>
            @endif

            @if (in_array($return->status, ['approved', 'picked_up'], true))
                <x-admin.card icon="bi-box-arrow-in-down" title="Mark as received" class="mt-3">
                    <p class="small text-muted">
                        Tick anything that came back in a sellable state — only those go back into stock.
                    </p>

                    <form method="POST" action="{{ route('admin.returns.receive', $return) }}">
                        @csrf @method('PATCH')

                        @foreach ($return->items as $line)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1"
                                       name="restock[{{ $line->id }}]" id="restock_{{ $line->id }}">
                                <label class="form-check-label small" for="restock_{{ $line->id }}">
                                    {{ $line->orderItem?->name ?? 'Item' }} × {{ $line->quantity }}
                                </label>
                            </div>
                        @endforeach

                        <button class="btn btn-sm btn-primary w-100 mt-3">Received</button>
                    </form>
                </x-admin.card>
            @endif

            @if (in_array($return->status, ['approved', 'picked_up', 'received'], true))
                <x-admin.card icon="bi-cash-coin" title="Settle it" class="mt-3">
                    @if ($return->type === 'return')
                        <form method="POST" action="{{ route('admin.returns.refund', $return) }}">
                            @csrf @method('PATCH')

                            <label for="refund_amount" class="form-label">Amount refunded</label>
                            <input type="number" step="0.01" min="0" id="refund_amount" name="refund_amount"
                                   value="{{ old('refund_amount', $return->refund_amount) }}"
                                   class="form-control form-control-sm mb-2" required>

                            <label for="refund_method" class="form-label">How</label>
                            <select id="refund_method" name="refund_method" class="form-select form-select-sm mb-2" required>
                                <option value="original">Back to the original payment</option>
                                <option value="bank">Bank transfer</option>
                                <option value="store_credit">Store credit</option>
                            </select>

                            <label for="refund_reference" class="form-label">Reference</label>
                            <input type="text" id="refund_reference" name="refund_reference" maxlength="120"
                                   value="{{ old('refund_reference') }}" class="form-control form-control-sm mb-2"
                                   placeholder="UTR, receipt number…">

                            @if ($gatewayPayment)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="send_to_gateway" value="1"
                                           id="send_to_gateway" @checked(old('send_to_gateway'))>
                                    <label class="form-check-label small" for="send_to_gateway">
                                        <strong>Send it through Razorpay now</strong>
                                    </label>
                                    <div class="form-text" style="font-size:.72rem">
                                        Against payment <code>{{ $gatewayPayment->gateway_payment_id }}</code>
                                        ({{ money($gatewayPayment->amount) }}). The reference is filled in
                                        from Razorpay's reply. Leave it unticked to record a refund you
                                        made some other way.
                                    </div>
                                </div>
                            @endif

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="notify" value="1" id="refund_notify" checked>
                                <label class="form-check-label small" for="refund_notify">Email the customer</label>
                            </div>

                            <button class="btn btn-sm btn-primary w-100">Refund</button>

                            <p class="small text-muted mt-2 mb-0">
                                @if ($gatewayPayment)
                                    Nothing is recorded if Razorpay refuses, so a failed refund never
                                    looks like a completed one.
                                @else
                                    This order has no captured online payment, so the refund is made at
                                    the counter or by transfer and recorded here.
                                @endif
                            </p>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.returns.replace', $return) }}">
                            @csrf @method('PATCH')

                            <label for="replace_message" class="form-label">Message to the customer</label>
                            <textarea id="replace_message" name="customer_message" rows="2" maxlength="500"
                                      class="form-control form-control-sm mb-2"
                                      placeholder="When the replacement will reach them"></textarea>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="notify" value="1" id="replace_notify" checked>
                                <label class="form-check-label small" for="replace_notify">Email the customer</label>
                            </div>

                            <button class="btn btn-sm btn-primary w-100">Mark as replaced</button>
                        </form>
                    @endif
                </x-admin.card>
            @endif

            @if ($return->isOpen())
                <x-admin.card icon="bi-x-circle" title="Settle without a refund" class="mt-3">
                    <p class="small text-muted">
                        For a claim sorted another way — advice given, a part posted out — so it
                        does not sit open or get falsely recorded as refunded.
                    </p>

                    <form method="POST" action="{{ route('admin.returns.close', $return) }}">
                        @csrf @method('PATCH')

                        <label for="close_message" class="form-label">Message to the customer</label>
                        <textarea id="close_message" name="customer_message" rows="2" maxlength="500"
                                  class="form-control form-control-sm mb-2"
                                  placeholder="How it was resolved"></textarea>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="notify" value="1" id="close_notify">
                            <label class="form-check-label small" for="close_notify">Email the customer</label>
                        </div>

                        <button class="btn btn-sm btn-outline-secondary w-100">Close this return</button>
                    </form>
                </x-admin.card>
            @endif

            @unless ($return->isOpen())
                <x-admin.card icon="bi-check2-all" title="Closed" class="mt-3">
                    <p class="small text-muted mb-0">
                        This return is {{ mb_strtolower($statuses[$return->status]) }}
                        @if ($return->resolved_at) on {{ $return->resolved_at->format('d M Y') }} @endif
                        @if ($return->resolver) by {{ $return->resolver->name }} @endif.
                    </p>
                </x-admin.card>
            @endunless
        @endcan
    </div>
</div>

@endsection
