@extends('admin.layouts.app')

@section('title', 'Quotation '.$quotation->number)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.quotations.index') }}">Quotations</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $quotation->number }}</li>
@endsection

@push('scripts')
    <script src="{{ asset_v('assets/js/line-editor.js') }}"></script>
@endpush

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        {{-- ---------------------------------------------------- the lines --}}
        <x-admin.card icon="bi-list-ul" title="Lines"
                      subtitle="{{ $quotation->isEditable() ? 'Prices include GST' : 'Locked — this quotation has been settled' }}">
            @if ($quotation->isEditable())
                @can('quotations.edit')
                    <form method="POST" action="{{ route('admin.quotations.items', $quotation) }}">
                        @csrf @method('PUT')

                        <div class="table-responsive">
                            <table class="ad-table" data-line-editor
                                   data-catalogue="#quotation-products"
                                   data-template="#quotation-line-template"
                                   data-amount="price">
                                <thead>
                                    <tr>
                                        <th style="width:190px">From the catalogue</th>
                                        <th>Description</th>
                                        <th style="width:72px" class="text-end">Qty</th>
                                        <th style="width:104px" class="text-end">Rate</th>
                                        <th style="width:72px" class="text-end">GST %</th>
                                        <th style="width:96px" class="text-end">Amount</th>
                                        <th style="width:38px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($quotation->items as $index => $item)
                                        @include('admin.quotations._line', ['item' => $item, 'index' => $index])
                                    @empty
                                        @include('admin.quotations._line', ['item' => null, 'index' => 0])
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
                            Leave the catalogue box empty for something you are making to order — just
                            describe it and price it.
                        </p>
                    </form>

                    {{-- The catalogue, read by the line editor when a product
                         is picked. Not a datalist on each row: one copy. --}}
                    <select id="quotation-products" class="d-none" tabindex="-1" aria-hidden="true">
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ $product->name }}"
                                    data-sku="{{ $product->sku }}"
                                    data-hsn="{{ $product->hsn_code }}"
                                    data-price="{{ $product->effective_price }}"
                                    data-rate="{{ $product->gst_rate }}">{{ $product->name }}</option>
                        @endforeach
                    </select>

                    <template id="quotation-line-template">
                        @include('admin.quotations._line', ['item' => null, 'index' => '__INDEX__'])
                    </template>
                @endcan
            @else
                <div class="table-responsive">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">GST</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotation->items as $item)
                                <tr>
                                    <td class="small">
                                        <strong>{{ $item->name }}</strong>
                                        @if ($item->description)
                                            <div class="text-muted">{{ $item->description }}</div>
                                        @endif
                                        @if ($item->isCustom())
                                            <span class="badge text-bg-light border">Made to order</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $item->quantity }}</td>
                                    <td class="text-end small">{{ money($item->unit_price) }}</td>
                                    <td class="text-end small text-muted">
                                        {{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%
                                    </td>
                                    <td class="text-end fw-semibold">{{ money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.card>

        {{-- ------------------------------------------------- the details --}}
        @can('quotations.edit')
            @if ($quotation->isEditable())
                <x-admin.card icon="bi-person" title="Customer &amp; delivery" class="mt-3">
                    <form method="POST" action="{{ route('admin.quotations.update', $quotation) }}">
                        @csrf @method('PUT')

                        @include('admin.quotations._details-form', ['enquiry' => $quotation->enquiry])

                        <div class="row g-2 mt-1">
                            <div class="col-md-3">
                                <label for="discount" class="form-label">Discount</label>
                                <input type="number" step="0.01" min="0" id="discount" name="discount"
                                       value="{{ old('discount', (float) $quotation->discount) }}"
                                       class="form-control form-control-sm" data-discount>
                            </div>
                            <div class="col-md-3">
                                <label for="delivery_charge" class="form-label">Delivery</label>
                                <input type="number" step="0.01" min="0" id="delivery_charge" name="delivery_charge"
                                       value="{{ old('delivery_charge', (float) $quotation->delivery_charge) }}"
                                       class="form-control form-control-sm" data-delivery>
                            </div>
                            <div class="col-md-3">
                                <label for="delivery_days" class="form-label">Delivery in (days)</label>
                                <input type="number" min="0" max="365" id="delivery_days" name="delivery_days"
                                       value="{{ old('delivery_days', $quotation->delivery_days) }}"
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label for="valid_until" class="form-label">Holds until</label>
                                <input type="date" id="valid_until" name="valid_until"
                                       value="{{ old('valid_until', $quotation->valid_until?->format('Y-m-d')) }}"
                                       class="form-control form-control-sm @error('valid_until') is-invalid @enderror">
                                @error('valid_until') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-8">
                                <label for="terms" class="form-label">Terms shown on the quotation</label>
                                <textarea id="terms" name="terms" rows="3" maxlength="5000"
                                          class="form-control form-control-sm">{{ old('terms', $quotation->terms) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="notes" class="form-label">Internal note</label>
                                <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                          class="form-control form-control-sm"
                                          placeholder="Not shown to the customer">{{ old('notes', $quotation->notes) }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-sm btn-primary mt-3">Save details</button>
                    </form>
                </x-admin.card>
            @endif
        @endcan
    </div>

    {{-- ----------------------------------------------------- the sidebar --}}
    <div class="col-xl-4">
        <x-admin.card icon="bi-file-earmark-text" title="{{ $quotation->number }}"
                      subtitle="Created {{ $quotation->created_at->format('d M Y') }}{{ $quotation->creator ? ' by '.$quotation->creator->name : '' }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $quotation->status_badge }}">{{ $quotation->status_label }}</span>
            </x-slot:actions>

            <dl class="fc-summary__rows mb-0">
                <dt>Subtotal</dt>
                <dd><span data-total-subtotal>{{ number_format((float) $quotation->subtotal, 2) }}</span></dd>

                @if ($quotation->discount > 0)
                    <dt class="is-discount">Discount</dt>
                    <dd class="is-discount">− {{ money($quotation->discount) }}</dd>
                @endif

                <dt>Delivery</dt>
                <dd>{{ $quotation->delivery_charge > 0 ? money($quotation->delivery_charge) : 'Free' }}</dd>

                <dt>GST within</dt>
                <dd><span data-total-tax>{{ number_format((float) $quotation->tax_total, 2) }}</span></dd>

                <dt class="fw-bold">Total</dt>
                <dd class="fw-bold"><span data-total-grand>{{ number_format((float) $quotation->grand_total, 2) }}</span></dd>
            </dl>

            <p class="small text-muted mt-2 mb-0">
                {{ $quotation->is_interstate ? 'IGST' : 'CGST + SGST' }}
                @if ($quotation->place_of_supply) · {{ $quotation->place_of_supply }} @endif
                @if ($quotation->valid_until)
                    <br>Holds until {{ $quotation->valid_until->format('d M Y') }}
                @endif
            </p>

            @if ($quotation->status !== 'draft')
                <a href="{{ route('admin.quotations.print', $quotation) }}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-secondary w-100 mt-3">
                    <i class="bi bi-printer"></i> Open / print
                </a>
            @endif
        </x-admin.card>

        @can('quotations.edit')
            <x-admin.card icon="bi-send" title="Where it stands" class="mt-3">
                @if ($quotation->order)
                    <p class="small mb-2">
                        Became order
                        <a href="{{ route('admin.orders.show', $quotation->order) }}">{{ $quotation->order->order_number }}</a>.
                    </p>
                @elseif ($quotation->isConvertible())
                    <p class="small text-muted">
                        Accepted{{ $quotation->accepted_at ? ' on '.$quotation->accepted_at->format('d M Y') : '' }}.
                        Turning it into an order copies these figures across exactly.
                    </p>
                    <form method="POST" action="{{ route('admin.quotations.convert', $quotation) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary w-100">Make it an order</button>
                    </form>
                @elseif ($quotation->status === 'draft')
                    <p class="small text-muted">
                        Not sent yet. Sending emails it to {{ $quotation->customer_email }} with a link
                        they can accept on.
                    </p>
                    <form method="POST" action="{{ route('admin.quotations.send', $quotation) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary w-100">Send to the customer</button>
                    </form>
                @elseif ($quotation->isAnswerable())
                    <p class="small text-muted">
                        Sent {{ $quotation->sent_at?->diffForHumans() }}. Waiting on them.
                        @if ($quotation->days_left !== null)
                            <br>{{ $quotation->days_left }} day(s) left.
                        @endif
                    </p>

                    <form method="POST" action="{{ route('admin.quotations.send', $quotation) }}" class="mb-2">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary w-100">Send again</button>
                    </form>

                    <hr>
                    <p class="small text-muted">If they answered by phone, record it here.</p>

                    <form method="POST" action="{{ route('admin.quotations.accepted', $quotation) }}" class="mb-2">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-primary w-100">They accepted</button>
                    </form>

                    <form method="POST" action="{{ route('admin.quotations.declined', $quotation) }}">
                        @csrf @method('PATCH')
                        <input type="text" name="decline_reason" maxlength="300"
                               class="form-control form-control-sm mb-2" placeholder="Why, if they said">
                        <button class="btn btn-sm btn-outline-danger w-100">They declined</button>
                    </form>
                @elseif ($quotation->hasLapsed())
                    <p class="small text-muted mb-0">
                        This lapsed on {{ $quotation->valid_until->format('d M Y') }}. Change the date
                        above and send it again, or start a fresh one.
                    </p>
                @else
                    <p class="small text-muted mb-0">
                        {{ $quotation->status_label }}@if ($quotation->decline_reason) — {{ $quotation->decline_reason }}@endif.
                    </p>
                @endif
            </x-admin.card>
        @endcan

        @if ($quotation->enquiry)
            <x-admin.card icon="bi-chat-left-text" title="From enquiry" class="mt-3">
                <a href="{{ route('admin.enquiries.show', $quotation->enquiry) }}" class="fw-semibold text-decoration-none">
                    {{ $quotation->enquiry->reference }}
                </a>
                <div class="ad-enquiry-message mt-2">{{ Str::limit($quotation->enquiry->message, 400) }}</div>
            </x-admin.card>
        @endif

        @if ($quotation->customer_note)
            <x-admin.card icon="bi-chat-quote" title="What they said" class="mt-3">
                <p class="small mb-0">{{ $quotation->customer_note }}</p>
            </x-admin.card>
        @endif
    </div>
</div>

@endsection
