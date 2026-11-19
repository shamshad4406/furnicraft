@extends('admin.layouts.app')

@section('title', 'Pincodes')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Pincodes</li>
@endsection

@section('content')

@if (session('pincode_errors'))
    <div class="alert alert-warning">
        <strong>Some lines were skipped:</strong>
        <ul class="mb-0 mt-2 ps-3 small">
            @foreach (session('pincode_errors') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card title="Serviceable pincodes" subtitle="{{ $pincodes->total() }} pincode(s)"
                      icon="bi-geo-alt" :flush="true">
            <x-slot:actions>
                <a href="{{ route('admin.delivery.pincodes.export') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download me-1"></i> Export
                </a>
            </x-slot:actions>

            <form method="GET" class="row g-2 p-3 border-bottom bg-light">
                <div class="col-md-5">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                           placeholder="Search pincode, city or state">
                </div>
                <div class="col-md-3">
                    <select name="zone" class="form-select form-select-sm">
                        <option value="">All zones</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" @selected(request('zone') == $zone->id)>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="yes" @selected(request('status') === 'yes')>Serviceable</option>
                        <option value="no" @selected(request('status') === 'no')>Not serviceable</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
                    <a href="{{ route('admin.delivery.pincodes.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>

            <x-admin.datatable :headers="['Pincode', 'City / State', 'Zone', 'Days', 'Status', ['label' => 'Actions', 'end' => true]]"
                               :rows="$pincodes" empty="No pincodes match this filter." empty-icon="bi-geo-alt">
                @foreach ($pincodes as $pincode)
                    <tr>
                        <td class="fw-semibold"><code>{{ $pincode->pincode }}</code></td>
                        <td class="lh-sm">
                            <div>{{ $pincode->city }}</div>
                            <div class="text-muted" style="font-size:.74rem">{{ $pincode->state }}</div>
                        </td>
                        <td class="text-muted" style="font-size:.82rem">{{ $pincode->zone?->name ?? '—' }}</td>
                        <td>{{ $pincode->effective_delivery_days }}</td>
                        <td>
                            <span class="badge {{ $pincode->is_serviceable ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $pincode->is_serviceable ? 'Serviceable' : 'No' }}
                            </span>
                            @if ($pincode->is_serviceable && ! $pincode->effective_cod)
                                <span class="badge text-bg-light border" style="font-size:.6rem">Prepaid</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('shipments.edit')
                                <form method="POST" action="{{ route('admin.delivery.pincodes.toggle', $pincode) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-outline-{{ $pincode->is_serviceable ? 'warning' : 'success' }}"
                                            data-bs-toggle="tooltip"
                                            title="{{ $pincode->is_serviceable ? 'Mark not serviceable' : 'Mark serviceable' }}">
                                        <i class="bi {{ $pincode->is_serviceable ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                    </button>
                                </form>
                            @endcan
                            @can('shipments.delete')
                                <form method="POST" action="{{ route('admin.delivery.pincodes.destroy', $pincode) }}"
                                      class="d-inline" data-confirm="Remove pincode {{ $pincode->pincode }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-admin.datatable>
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        @can('shipments.create')
            {{-- The form has to wrap the card, not sit inside it. A
                 <x-slot:footer> is the card component's slot: Blade lifts it
                 out of the slot content and renders it in the card footer,
                 which is outside any <form> written inside the card. The
                 submit button then belongs to no form and the button does
                 nothing at all when clicked. --}}
            <form method="POST" action="{{ route('admin.delivery.pincodes.store') }}" novalidate>
                @csrf

                <x-admin.card title="Add one pincode" icon="bi-plus-circle">
                    <div class="row g-3">
                        <x-admin.input name="pincode" label="Pincode" col="col-6" required maxlength="6" />
                        <x-admin.input name="delivery_days" label="Days" type="number" min="1" col="col-6"
                                       help="Blank = zone default" />
                        <x-admin.input name="city" label="City" col="col-12" required />
                        <x-admin.input name="state" label="State" col="col-12" required />
                        <x-admin.select name="delivery_zone_id" label="Delivery zone" col="col-12" id="single-zone"
                                        :options="$zones->pluck('name', 'id')->all()" placeholder="— None —" />
                        <x-admin.switcher name="is_serviceable" col="col-6" :checked="true">Serviceable</x-admin.switcher>
                        <x-admin.switcher name="cod_available" col="col-6" :checked="true">COD</x-admin.switcher>
                    </div>

                    <x-slot:footer>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Add Pincode
                        </button>
                    </x-slot:footer>
                </x-admin.card>
            </form>

            <form method="POST" action="{{ route('admin.delivery.pincodes.import') }}" novalidate>
                @csrf

                <x-admin.card title="Bulk add" subtitle="One per line: pincode, city, state"
                              icon="bi-list-ul" class="mt-3">
                    <div class="row g-3">
                        <x-admin.select name="delivery_zone_id" label="Assign to zone" col="col-12" required id="bulk-zone"
                                        :options="$zones->pluck('name', 'id')->all()" placeholder="Select a zone" />
                        <x-admin.textarea name="rows" label="Pincodes" rows="8"
                                          placeholder="110001, New Delhi, Delhi&#10;201301, Noida, Uttar Pradesh"
                                          style="font-family:ui-monospace,monospace;font-size:.82rem" />
                    </div>

                    <x-slot:footer>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-upload me-1"></i> Add These Pincodes
                        </button>
                    </x-slot:footer>
                </x-admin.card>
            </form>
        @endcan
    </div>
</div>

@endsection
