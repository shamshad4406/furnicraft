{{--
    Returns raised against this order.

    $order, $returnableItems, $returnTypes, $returnReasons
--}}
@php
    $canClaim = $returnableItems->filter(fn ($row) => $row['available'] > 0);
@endphp

<x-admin.card icon="bi-arrow-return-left" title="Returns"
              subtitle="{{ $order->returns->count() }} raised" class="mt-3">

    @forelse ($order->returns as $claim)
        <div class="ad-consignment">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div>
                    <a href="{{ route('admin.returns.show', $claim) }}" class="fw-semibold text-decoration-none">
                        {{ $claim->reference }}
                    </a>
                    <span class="badge text-bg-{{ $claim->status_badge }} ms-1">
                        {{ \App\Models\OrderReturn::STATUSES[$claim->status] }}
                    </span>
                    <span class="badge text-bg-light border ms-1">
                        {{ \App\Models\OrderReturn::TYPES[$claim->type] }}
                    </span>

                    <div class="small text-muted mt-1">
                        {{ $claim->reason_label }} ·
                        {{ $claim->items->sum('quantity') }} unit(s) ·
                        raised {{ $claim->requested_at->format('d M Y') }}
                    </div>

                    @if ($claim->refund_amount > 0)
                        <div class="small text-muted">Refund {{ money($claim->refund_amount) }}</div>
                    @endif
                </div>

                <a href="{{ route('admin.returns.show', $claim) }}" class="btn btn-sm btn-outline-secondary">Handle</a>
            </div>
        </div>
    @empty
        <p class="small text-muted">Nothing has been sent back.</p>
    @endforelse

    @can('orders.edit')
        @if ($order->status === 'delivered' && $canClaim->isNotEmpty())
            <hr>
            <details>
                <summary class="fw-semibold small mb-2" style="cursor:pointer">Raise a return for the customer</summary>

                <form method="POST" action="{{ route('admin.returns.store', $order) }}" class="mt-2">
                    @csrf

                    <div class="table-responsive mb-2">
                        <table class="ad-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Bought</th>
                                    <th class="text-end">Already claimed</th>
                                    <th style="width:110px" class="text-end">Returning</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($canClaim as $itemId => $row)
                                    <tr>
                                        <td class="small">{{ $row['item']->name }}</td>
                                        <td class="text-end small">{{ $row['ordered'] }}</td>
                                        <td class="text-end small text-muted">{{ $row['claimed'] }}</td>
                                        <td>
                                            <input type="number" name="quantities[{{ $itemId }}]" value="0"
                                                   min="0" max="{{ $row['available'] }}"
                                                   class="form-control form-control-sm text-end"
                                                   aria-label="Quantity returning of {{ $row['item']->name }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="return_type" class="form-label">What they want</label>
                            <select id="return_type" name="type" class="form-select form-select-sm" required>
                                @foreach ($returnTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="return_reason" class="form-label">Reason</label>
                            <select id="return_reason" name="reason" class="form-select form-select-sm" required>
                                @foreach ($returnReasons as $value => $label)
                                    <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="return_details" class="form-label">What they said</label>
                            <input type="text" id="return_details" name="details" value="{{ old('details') }}"
                                   maxlength="2000" class="form-control form-control-sm">
                        </div>
                    </div>

                    <button class="btn btn-sm btn-primary mt-3">Raise return</button>
                </form>
            </details>
        @elseif ($order->status !== 'delivered')
            <p class="small text-muted mb-0">
                A return can only be raised once the order has been delivered.
            </p>
        @endif
    @endcan
</x-admin.card>
