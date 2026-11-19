@extends('admin.layouts.app')

@section('title', 'Delivery Zones')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Delivery Zones</li>
@endsection

@section('content')

<div class="alert alert-light border d-flex gap-2 small">
    <i class="bi bi-info-circle-fill text-primary"></i>
    <div>
        Zones set the default delivery time for every pincode mapped to them. The charges are stored
        now and applied at checkout in Phase 4.
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        @foreach ($zones as $zone)
            {{-- The form wraps the card. A <x-slot:footer> written inside a
                 form is lifted out of the slot content by Blade and rendered
                 in the card footer, outside that form, leaving the submit
                 button attached to nothing. --}}
            <form method="POST" action="{{ route('admin.delivery.zones.update', $zone) }}" novalidate>
                @csrf @method('PUT')

                <x-admin.card :title="$zone->name" :subtitle="$zone->description" icon="bi-map"
                              class="{{ $loop->first ? '' : 'mt-3' }}">
                    <x-slot:actions>
                        <span class="badge text-bg-light border">{{ $zone->pincodes_count }} pincode(s)</span>
                        <span class="badge {{ $zone->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $zone->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </x-slot:actions>

                    <div class="row g-3">
                        <x-admin.input name="name" label="Zone name" :value="$zone->name" required col="col-md-6"
                                       :id="'name-'.$zone->id" />
                        <x-admin.input name="description" label="Covers" :value="$zone->description" col="col-md-6"
                                       :id="'description-'.$zone->id" />
                        <x-admin.input name="delivery_days" label="Delivery days" type="number" min="1"
                                       :value="$zone->delivery_days" required col="col-md-3"
                                       :id="'delivery_days-'.$zone->id" />
                        <x-admin.input name="base_charge" label="Base charge" type="number" step="0.01" min="0"
                                       :value="$zone->base_charge" required col="col-md-3"
                                       :id="'base_charge-'.$zone->id" />
                        <x-admin.input name="per_kg_charge" label="Per kg" type="number" step="0.01" min="0"
                                       :value="$zone->per_kg_charge" required col="col-md-3"
                                       :id="'per_kg_charge-'.$zone->id" />
                        <x-admin.input name="free_above" label="Free above" type="number" step="0.01" min="0"
                                       :value="$zone->free_above" col="col-md-3"
                                       :id="'free_above-'.$zone->id" help="Blank = never free" />
                        <x-admin.switcher name="cod_available" col="col-md-6" :checked="$zone->cod_available"
                                          :id="'cod_available-'.$zone->id">
                            Cash on delivery allowed
                        </x-admin.switcher>
                        <x-admin.switcher name="is_active" col="col-md-6" :checked="$zone->is_active"
                                          :id="'is_active-'.$zone->id">
                            Zone is active
                        </x-admin.switcher>
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            @can('shipments.edit')
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-check-lg me-1"></i> Save Zone
                                </button>
                            @endcan
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </form>
        @endforeach

        @if ($zones->isEmpty())
            <x-admin.card>
                <div class="ad-empty"><i class="bi bi-map"></i>No delivery zones yet. Create one on the right.</div>
            </x-admin.card>
        @endif
    </div>

    <div class="col-xl-4">
        @can('shipments.create')
            <form method="POST" action="{{ route('admin.delivery.zones.store') }}" novalidate>
                @csrf

                <x-admin.card title="Add a zone" icon="bi-plus-circle">
                    <div class="row g-3">
                        <x-admin.input name="name" label="Zone name" col="col-12" required id="new-zone-name"
                                       placeholder="South India" />
                        <x-admin.input name="description" label="Covers" col="col-12" id="new-zone-description"
                                       placeholder="Karnataka, Tamil Nadu, Kerala" />
                        <x-admin.input name="delivery_days" label="Delivery days" type="number" min="1" value="7"
                                       col="col-6" required id="new-zone-days" />
                        <x-admin.input name="base_charge" label="Base charge" type="number" step="0.01" min="0"
                                       value="0" col="col-6" required id="new-zone-base" />
                        <x-admin.input name="per_kg_charge" label="Per kg" type="number" step="0.01" min="0"
                                       value="0" col="col-6" required id="new-zone-perkg" />
                        <x-admin.input name="free_above" label="Free above" type="number" step="0.01" min="0"
                                       col="col-6" id="new-zone-free" />
                        <x-admin.switcher name="cod_available" col="col-6" :checked="true" id="new-zone-cod">COD</x-admin.switcher>
                        <x-admin.switcher name="is_active" col="col-6" :checked="true" id="new-zone-active">Active</x-admin.switcher>
                    </div>

                    <x-slot:footer>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Create Zone
                        </button>
                    </x-slot:footer>
                </x-admin.card>
            </form>
        @endcan
    </div>
</div>

@endsection
