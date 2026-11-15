@extends('admin.layouts.app')

@section('title', 'Purchase order '.$order->number)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.purchases.index') }}">Purchases</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $order->number }}</li>
@endsection

@push('scripts')
    <script src="{{ asset_v('assets/js/line-editor.js') }}"></script>
@endpush

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        {{-- ---------------------------------------------------- the lines --}}
        <x-admin.card icon="bi-list-ul" title="Lines"
                      subtitle="{{ $order->isEditable() ? 'Costs include GST' : 'Locked — this order has been placed' }}">

            @if ($order->isEditable())
                @can('purchases.edit')
                    <form method="POST" action="{{ route('admin.purchases.items', $order) }}">
                        @csrf @method('PUT')

                        <div class="table-responsive">
                            <table class="ad-table" data-line-editor
                                   data-catalogue="#purchase-products"
                                   data-template="#purchase-line-template"
                                   data-amount="cost">
                                <thead>
                                    <tr>
                                        <th style="width:220px">Product</th>
                                        <th>Description</th>
                                        <th style="width:72px" class="text-end">Qty</th>
                                        <th style="width:110px" class="text-end">Unit cost</th>
                                        <th style="width:72px" class="text-end">GST %</th>
                                        <th style="width:100px" class="text-end">Amount</th>
                                        <th style="width:38px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($order->items as $index => $item)
                                        @include('admin.purchases._line', ['item' => $item, 'index' => $index])
                                    @empty
                                        @include('admin.purchases._line', ['item' => null, 'index' => 0])
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @error('items') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                        @error('items.*.name') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-line>
                                <i class="bi bi-plus-lg"></i> Add a line
                            </button>
                            <button class="btn btn-sm btn-primary">Save lines</button>
                        </div>

                        <p class="small text-muted mt-2 mb-0">
                            A line has to name a catalogue product to be booked into stock when it arrives.
                        </p>
                    </form>

                    <select id="purchase-products" class="d-none" tabindex="-1" aria-hidden="true">
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ $product->name }}"
                                    data-sku="{{ $product->sku }}"
                                    data-cost="{{ $product->cost_price }}"
                                    data-rate="{{ $product->gst_rate }}">{{ $product->name }}</option>
                        @endforeach
                    </select>

                    <template id="purchase-line-template">
                        @include('admin.purchases._line', ['item' => null, 'index' => '__INDEX__'])
                    </template>
                @endcan
            @else
                <div class="table-responsive">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Unit cost</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="small">
                                        <strong>{{ $item->name }}</strong>
                                        @if ($item->sku)
                                            <div class="text-muted">{{ $item->sku }}</div>
                                        @endif
                                        @unless ($item->product_id)
                                            <span class="badge text-bg-warning">Not a catalogue product</span>
                                        @endunless
                                    </td>
                                    <td class="text-end">{{ $item->quantity }}</td>
                                    <td class="text-end {{ $item->isFullyReceived() ? 'text-success' : 'text-warning' }}">
                                        {{ $item->received_quantity }}
                                        @unless ($item->isFullyReceived())
                                            <div class="small">{{ $item->outstanding }} to come</div>
                                        @endunless
                                    </td>
                                    <td class="text-end small">{{ money($item->unit_cost) }}</td>
                                    <td class="text-end fw-semibold">{{ money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.card>

        {{-- ------------------------------------------------- receiving --}}
        @can('purchases.edit')
            @if ($order->isReceivable())
                <x-admin.card icon="bi-box-arrow-in-down" title="Book a delivery in" class="mt-3">
                    <p class="small text-muted">
                        Enter what actually turned up. Those units go straight onto the shelf, and the
                        stock ledger records where they came from.
                    </p>

                    <form method="POST" action="{{ route('admin.purchases.receive', $order) }}">
                        @csrf

                        <div class="table-responsive mb-2">
                            <table class="ad-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Ordered</th>
                                        <th class="text-end">Already in</th>
                                        <th style="width:120px" class="text-end">Arriving now</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->items as $item)
                                        @continue($item->isFullyReceived())
                                        <tr>
                                            <td class="small">
                                                {{ $item->name }}
                                                @unless ($item->product_id)
                                                    <div class="text-warning">Not linked to a product — cannot be stocked</div>
                                                @endunless
                                            </td>
                                            <td class="text-end small">{{ $item->quantity }}</td>
                                            <td class="text-end small text-muted">{{ $item->received_quantity }}</td>
                                            <td>
                                                <input type="number" name="quantities[{{ $item->id }}]"
                                                       value="{{ $item->outstanding }}" min="0" max="{{ $item->outstanding }}"
                                                       class="form-control form-control-sm text-end"
                                                       aria-label="Quantity arriving of {{ $item->name }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="supplier_invoice_number" class="form-label">Their invoice number</label>
                                <input type="text" id="supplier_invoice_number" name="supplier_invoice_number"
                                       maxlength="60" value="{{ old('supplier_invoice_number', $order->supplier_invoice_number) }}"
                                       class="form-control form-control-sm">
                            </div>
                        </div>

                        <button class="btn btn-sm btn-primary mt-3">Book it in</button>
                    </form>
                </x-admin.card>
            @endif
        @endcan
    </div>

    {{-- ------------------------------------------------------ the sidebar --}}
    <div class="col-xl-4">
        <x-admin.card icon="bi-cart-plus" title="{{ $order->number }}"
                      subtitle="Raised {{ $order->created_at->format('d M Y') }}{{ $order->creator ? ' by '.$order->creator->name : '' }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $order->status_badge }}">{{ $order->status_label }}</span>
            </x-slot:actions>

            <div class="ad-detail__label">Supplier</div>
            <a href="{{ route('admin.suppliers.show', $order->supplier) }}" class="fw-semibold text-decoration-none">
                {{ $order->supplier->name }}
            </a>
            <div class="small text-muted">{{ $order->supplier->contact_person }}</div>
            <div class="small text-muted">{{ $order->supplier->phone }}</div>

            <hr>

            <dl class="fc-summary__rows mb-0">
                <dt>Subtotal</dt>
                <dd><span data-total-subtotal>{{ number_format((float) $order->subtotal, 2) }}</span></dd>

                @if ($order->other_charges > 0)
                    <dt>Other charges</dt>
                    <dd>{{ money($order->other_charges) }}</dd>
                @endif

                <dt>GST within</dt>
                <dd><span data-total-tax>{{ number_format((float) $order->tax_total, 2) }}</span></dd>

                <dt class="fw-bold">Total</dt>
                <dd class="fw-bold"><span data-total-grand>{{ number_format((float) $order->grand_total, 2) }}</span></dd>
            </dl>

            @if ($order->expected_on)
                <p class="small {{ $order->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }} mt-2 mb-0">
                    Expected {{ $order->expected_on->format('d M Y') }}
                    @if ($order->isOverdue()) — overdue @endif
                </p>
            @endif

            @if ($order->cancel_reason)
                <div class="alert alert-danger small mt-3 mb-0">
                    <strong>Cancelled.</strong> {{ $order->cancel_reason }}
                </div>
            @endif
        </x-admin.card>

        @can('purchases.edit')
            @if ($order->isEditable())
                <x-admin.card icon="bi-send" title="Place it" class="mt-3">
                    <p class="small text-muted">
                        Once placed, the lines are fixed — it is a record of what you agreed to buy.
                    </p>
                    <form method="POST" action="{{ route('admin.purchases.place', $order) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary w-100">Place this order</button>
                    </form>
                </x-admin.card>

                <x-admin.card icon="bi-sliders" title="Details" class="mt-3">
                    <form method="POST" action="{{ route('admin.purchases.update', $order) }}">
                        @csrf @method('PUT')

                        <label for="expected_on" class="form-label">Expected</label>
                        <input type="date" id="expected_on" name="expected_on"
                               value="{{ old('expected_on', $order->expected_on?->format('Y-m-d')) }}"
                               class="form-control form-control-sm mb-2">

                        <label for="other_charges" class="form-label">Freight &amp; other charges</label>
                        <input type="number" step="0.01" min="0" id="other_charges" name="other_charges"
                               value="{{ old('other_charges', (float) $order->other_charges) }}"
                               class="form-control form-control-sm mb-2">

                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                  class="form-control form-control-sm mb-2">{{ old('notes', $order->notes) }}</textarea>

                        <button class="btn btn-sm btn-outline-secondary w-100">Save details</button>
                    </form>
                </x-admin.card>
            @endif

            @if (! in_array($order->status, ['received', 'cancelled'], true))
                <x-admin.card icon="bi-x-circle" title="Cancel it" class="mt-3">
                    <form method="POST" action="{{ route('admin.purchases.cancel', $order) }}">
                        @csrf @method('PATCH')
                        <input type="text" name="cancel_reason" maxlength="300"
                               class="form-control form-control-sm mb-2" placeholder="Why">
                        <button class="btn btn-sm btn-outline-danger w-100"
                                data-confirm="Cancel this purchase order?">Cancel the order</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">
                        Not possible once part of it has arrived — that stock would be left unexplained.
                    </p>
                </x-admin.card>
            @endif
        @endcan
    </div>
</div>

@endsection
