@extends('admin.layouts.app')

@section('title', 'Shipments')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Shipments</li>
@endsection

@section('content')

<x-admin.card title="Shipments" icon="bi-truck"
              subtitle="{{ $shipments->total() }} consignment(s) · {{ $openCount }} still out" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.shipments.index', ['flag' => 'open']) }}"
           class="btn btn-sm {{ request('flag') === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}">
            Still out ({{ $openCount }})
        </a>
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Consignment, tracking number, courier, order or customer">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="assigned_to" class="form-select form-select-sm">
                <option value="">Anyone</option>
                @foreach ($staff as $person)
                    <option value="{{ $person->id }}" @selected(request('assigned_to') == $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.shipments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Consignment', 'Order', 'Carrier', 'Units', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$shipments" empty="No consignments match this filter." empty-icon="bi-truck">
        @foreach ($shipments as $shipment)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $shipment->consignment_number }}</div>
                    <div class="small text-muted">
                        @if ($shipment->dispatched_at)
                            Sent {{ $shipment->dispatched_at->format('d M Y') }}
                        @else
                            Not sent yet
                        @endif
                        @if ($shipment->expected_delivery_on)
                            · expected {{ $shipment->expected_delivery_on->format('d M') }}
                        @endif
                    </div>
                </td>

                <td>
                    <a href="{{ route('admin.orders.show', $shipment->order) }}" class="small text-decoration-none">
                        {{ $shipment->order->order_number }}
                    </a>
                    <div class="small text-muted">
                        {{ $shipment->order->customer_name }} — {{ $shipment->order->shipping_pincode }}
                    </div>
                </td>

                <td class="small">
                    @if ($shipment->courier)
                        {{ $shipment->courier }}
                        @if ($shipment->tracking_number)
                            <div class="text-muted">{{ $shipment->tracking_number }}</div>
                        @endif
                    @elseif ($shipment->assignee)
                        <span class="text-muted">Own delivery</span>
                        <div>{{ $shipment->assignee->name }}</div>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>

                <td class="small">
                    {{ $shipment->items_count }} line(s)
                    <div class="text-muted">{{ $shipment->packages }} package(s)</div>
                </td>

                <td>
                    <span class="badge text-bg-{{ $shipment->status_badge }}">{{ $statuses[$shipment->status] }}</span>
                    @if ($shipment->delivered_at)
                        <div class="text-muted" style="font-size:.72rem">
                            {{ $shipment->delivered_at->format('d M, H:i') }}
                        </div>
                    @endif
                </td>

                <td class="text-end">
                    @can('shipments.edit')
                        @unless ($shipment->isClosed())
                            <form method="POST" action="{{ route('admin.shipments.status', $shipment) }}"
                                  class="d-inline-flex gap-1">
                                @csrf @method('PATCH')
                                <select name="status" class="form-select form-select-sm" style="width:auto"
                                        aria-label="Status for {{ $shipment->consignment_number }}">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($shipment->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="notify" value="1">
                                <button class="btn btn-sm btn-outline-primary">Set</button>
                            </form>
                        @endunless
                    @endcan

                    <a href="{{ route('admin.orders.show', $shipment->order) }}"
                       class="btn btn-sm btn-outline-secondary" title="Open the order"><i class="bi bi-eye"></i></a>
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
