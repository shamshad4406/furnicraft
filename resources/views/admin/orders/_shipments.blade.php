{{--
    Consignments against this order.

    $order, $pendingItems, $shipmentStatuses, $deliveryStaff
--}}
@php
    $stillToSend = $pendingItems->filter(fn ($row) => $row['pending'] > 0);
@endphp

<x-admin.card icon="bi-truck" title="Consignments"
              subtitle="{{ $order->shipments->count() }} sent · {{ $stillToSend->sum('pending') }} unit(s) still to go"
              class="mt-3">

    @forelse ($order->shipments as $shipment)
        <div class="ad-consignment">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div>
                    <strong>{{ $shipment->consignment_number }}</strong>
                    <span class="badge text-bg-{{ $shipment->status_badge }} ms-1">
                        {{ $shipmentStatuses[$shipment->status] }}
                    </span>

                    <div class="small text-muted mt-1">
                        @if ($shipment->courier)
                            {{ $shipment->courier }}@if ($shipment->tracking_number) · {{ $shipment->tracking_number }}@endif
                        @elseif ($shipment->assignee)
                            Our own delivery — {{ $shipment->assignee->name }}
                        @else
                            No courier recorded
                        @endif
                    </div>

                    <div class="small text-muted">
                        {{ $shipment->items->sum('quantity') }} unit(s) ·
                        {{ $shipment->packages }} package(s)
                        @if ($shipment->weight) · {{ $shipment->weight }} kg @endif
                        @if ($shipment->expected_delivery_on)
                            · expected {{ $shipment->expected_delivery_on->format('d M Y') }}
                        @endif
                    </div>

                    <ul class="small text-muted mb-0 mt-1 ps-3">
                        @foreach ($shipment->items as $line)
                            <li>{{ $line->orderItem?->name ?? 'Item' }} × {{ $line->quantity }}</li>
                        @endforeach
                    </ul>

                    @if ($shipment->delivered_at)
                        <div class="small text-success mt-1">
                            Delivered {{ $shipment->delivered_at->format('d M Y, H:i') }}
                            @if ($shipment->received_by) · received by {{ $shipment->received_by }} @endif
                        </div>
                    @endif

                    @if ($shipment->notes)
                        <div class="small text-muted mt-1"><em>{{ $shipment->notes }}</em></div>
                    @endif
                </div>

                @can('shipments.edit')
                    @unless ($shipment->isClosed())
                        <form method="POST" action="{{ route('admin.shipments.status', $shipment) }}"
                              class="d-flex gap-1 align-items-start">
                            @csrf @method('PATCH')
                            <select name="status" class="form-select form-select-sm" style="width:auto" aria-label="Consignment status">
                                @foreach ($shipmentStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected($shipment->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-primary">Set</button>
                        </form>
                    @endunless
                @endcan
            </div>

            @if ($shipment->tracking_url)
                <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer"
                   class="small">Track with the courier <i class="bi bi-box-arrow-up-right"></i></a>
            @endif

            @can('shipments.edit')
                <div class="mt-2">
                    <button class="btn btn-sm btn-link p-0 small" type="button"
                            data-bs-toggle="collapse" data-bs-target="#edit-shipment-{{ $shipment->id }}"
                            aria-expanded="false" aria-controls="edit-shipment-{{ $shipment->id }}">
                        Edit courier &amp; tracking
                    </button>
                </div>

                {{-- A tracking number usually arrives after the van has gone,
                     so a consignment has to be editable once it exists. --}}
                <div class="collapse mt-2" id="edit-shipment-{{ $shipment->id }}">
                    <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}">
                        @csrf @method('PUT')

                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="edit_courier_{{ $shipment->id }}" class="form-label">Courier</label>
                                <input type="text" id="edit_courier_{{ $shipment->id }}" name="courier"
                                       value="{{ $shipment->courier }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_tracking_{{ $shipment->id }}" class="form-label">Tracking number</label>
                                <input type="text" id="edit_tracking_{{ $shipment->id }}" name="tracking_number"
                                       value="{{ $shipment->tracking_number }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_url_{{ $shipment->id }}" class="form-label">Tracking link</label>
                                <input type="url" id="edit_url_{{ $shipment->id }}" name="tracking_url"
                                       value="{{ $shipment->tracking_url }}" class="form-control form-control-sm"
                                       placeholder="https://">
                            </div>

                            <div class="col-md-3">
                                <label for="edit_packages_{{ $shipment->id }}" class="form-label">Packages</label>
                                <input type="number" id="edit_packages_{{ $shipment->id }}" name="packages"
                                       value="{{ $shipment->packages }}" min="1" max="999"
                                       class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_weight_{{ $shipment->id }}" class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" id="edit_weight_{{ $shipment->id }}" name="weight"
                                       value="{{ $shipment->weight }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_assigned_{{ $shipment->id }}" class="form-label">Delivered by</label>
                                <select id="edit_assigned_{{ $shipment->id }}" name="assigned_to"
                                        class="form-select form-select-sm">
                                    <option value="">A courier</option>
                                    @foreach ($deliveryStaff as $person)
                                        <option value="{{ $person->id }}" @selected($shipment->assigned_to == $person->id)>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_expected_{{ $shipment->id }}" class="form-label">Expected</label>
                                <input type="date" id="edit_expected_{{ $shipment->id }}" name="expected_delivery_on"
                                       value="{{ $shipment->expected_delivery_on?->format('Y-m-d') }}"
                                       class="form-control form-control-sm">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_received_{{ $shipment->id }}" class="form-label">Received by</label>
                                <input type="text" id="edit_received_{{ $shipment->id }}" name="received_by"
                                       value="{{ $shipment->received_by }}" maxlength="120"
                                       class="form-control form-control-sm" placeholder="Who signed for it">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_notes_{{ $shipment->id }}" class="form-label">Note</label>
                                <input type="text" id="edit_notes_{{ $shipment->id }}" name="notes"
                                       value="{{ $shipment->notes }}" maxlength="500"
                                       class="form-control form-control-sm">
                            </div>
                        </div>

                        <button class="btn btn-sm btn-outline-primary mt-2">Save consignment</button>
                    </form>
                </div>
            @endcan
        </div>
    @empty
        <p class="small text-muted">Nothing has been sent for this order yet.</p>
    @endforelse

    @can('shipments.create')
        @if ($stillToSend->isNotEmpty() && ! in_array($order->status, ['pending', 'cancelled'], true))
            <hr>
            <details @if ($order->shipments->isEmpty()) open @endif>
                <summary class="fw-semibold small mb-2" style="cursor:pointer">Send a consignment</summary>

                <form method="POST" action="{{ route('admin.shipments.store', $order) }}" class="mt-2">
                    @csrf

                    <div class="table-responsive mb-2">
                        <table class="ad-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Ordered</th>
                                    <th class="text-end">Sent</th>
                                    <th style="width:110px" class="text-end">Send now</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stillToSend as $itemId => $row)
                                    <tr>
                                        <td class="small">{{ $row['item']->name }}</td>
                                        <td class="text-end small">{{ $row['ordered'] }}</td>
                                        <td class="text-end small text-muted">{{ $row['shipped'] }}</td>
                                        <td>
                                            <input type="number" name="quantities[{{ $itemId }}]"
                                                   value="{{ $row['pending'] }}" min="0" max="{{ $row['pending'] }}"
                                                   class="form-control form-control-sm text-end"
                                                   aria-label="Quantity to send of {{ $row['item']->name }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="courier" class="form-label">Courier</label>
                            <input type="text" id="courier" name="courier" value="{{ old('courier') }}"
                                   class="form-control form-control-sm" placeholder="Blue Dart, own van…">
                        </div>
                        <div class="col-md-4">
                            <label for="tracking_number" class="form-label">Tracking number</label>
                            <input type="text" id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label for="tracking_url" class="form-label">Tracking link</label>
                            <input type="url" id="tracking_url" name="tracking_url" value="{{ old('tracking_url') }}"
                                   class="form-control form-control-sm" placeholder="https://">
                        </div>

                        <div class="col-md-3">
                            <label for="packages" class="form-label">Packages</label>
                            <input type="number" id="packages" name="packages" value="{{ old('packages', 1) }}"
                                   min="1" max="999" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.01" id="weight" name="weight" value="{{ old('weight') }}"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label for="assigned_to" class="form-label">Delivered by</label>
                            <select id="assigned_to" name="assigned_to" class="form-select form-select-sm">
                                <option value="">A courier</option>
                                @foreach ($deliveryStaff as $person)
                                    <option value="{{ $person->id }}" @selected(old('assigned_to') == $person->id)>
                                        {{ $person->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="expected_delivery_on" class="form-label">Expected</label>
                            <input type="date" id="expected_delivery_on" name="expected_delivery_on"
                                   value="{{ old('expected_delivery_on') }}" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-6">
                            <label for="shipment_status" class="form-label">Status</label>
                            <select id="shipment_status" name="status" class="form-select form-select-sm">
                                @foreach ($shipmentStatuses as $value => $label)
                                    @continue(in_array($value, ['delivered', 'failed', 'returned'], true))
                                    <option value="{{ $value }}" @selected(old('status', 'dispatched') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="shipment_notes" class="form-label">Note</label>
                            <input type="text" id="shipment_notes" name="notes" value="{{ old('notes') }}"
                                   maxlength="500" class="form-control form-control-sm">
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify" value="1"
                                       id="shipment_notify" checked>
                                <label class="form-check-label small" for="shipment_notify">
                                    Email the customer that it is on its way
                                </label>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-sm btn-primary mt-3">Create consignment</button>
                </form>
            </details>
        @elseif ($stillToSend->isEmpty() && $order->shipments->isNotEmpty())
            <p class="small text-muted mb-0"><i class="bi bi-check2-all"></i> Everything on this order has been sent.</p>
        @endif
    @endcan
</x-admin.card>
