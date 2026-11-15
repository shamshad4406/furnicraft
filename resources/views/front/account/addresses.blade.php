@extends('front.layouts.app')

@section('title', 'My Addresses')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>My Addresses</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('account.dashboard') }}">Account</a></li>
                <li class="breadcrumb-item active" aria-current="page">Addresses</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-3">@include('front.account._nav')</div>

            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-xl-7">
                        @if ($addresses->isEmpty())
                            <div class="fc-empty-state">
                                <i class="bi bi-geo-alt"></i>
                                <h2>No saved addresses</h2>
                                <p>Add one here and it will be ready at checkout.</p>
                            </div>
                        @else
                            @foreach ($addresses as $address)
                                @php
                                    // Reopen the card whose edit form failed validation.
                                    $editFailed = $errors->any() && (string) old('address_id') === (string) $address->id;
                                @endphp

                                <div class="fc-panel mb-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <strong>{{ $address->name }}</strong>
                                            <span class="badge text-bg-light border ms-1">{{ $types[$address->type] }}</span>
                                            {{-- Only when it says something the type badge does not. --}}
                                            @if ($address->label && ! Str::is($types[$address->type], $address->label))
                                                <span class="badge text-bg-light border ms-1">{{ $address->label }}</span>
                                            @endif
                                            @if ($address->is_default_shipping)
                                                <span class="badge text-bg-warning ms-1">Default</span>
                                            @endif
                                            <p class="small text-muted mt-2 mb-1">{{ $address->single_line }}</p>
                                            <p class="small text-muted mb-0">{{ $address->phone }}</p>
                                        </div>

                                        <div class="d-flex flex-column gap-1">
                                            <button class="btn btn-sm btn-outline-secondary w-100"
                                                    type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#edit-address-{{ $address->id }}"
                                                    aria-expanded="{{ $editFailed ? 'true' : 'false' }}"
                                                    aria-controls="edit-address-{{ $address->id }}">Edit</button>

                                            @unless ($address->is_default_shipping)
                                                <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-secondary w-100">Make default</button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('account.addresses.destroy', $address) }}"
                                                  data-confirm="Remove this address?">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger w-100">Remove</button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="collapse @if ($editFailed) show @endif" id="edit-address-{{ $address->id }}">
                                        <hr class="my-3">
                                        <form method="POST" action="{{ route('account.addresses.update', $address) }}" novalidate>
                                            @csrf @method('PUT')
                                            @include('front.account._address-fields', ['address' => $address, 'types' => $types])
                                            <button type="submit" class="btn btn-navy mt-3">Save changes</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <div class="col-xl-5">
                        <div class="fc-panel">
                            <h2 class="fc-panel__title">Add an address</h2>

                            <form method="POST" action="{{ route('account.addresses.store') }}" novalidate>
                                @csrf
                                @include('front.account._address-fields', ['address' => null, 'types' => $types])
                                <button type="submit" class="btn btn-navy w-100 mt-3">Save address</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
