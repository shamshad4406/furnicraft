{{--
    The address field set, shared by the "Add an address" panel and the inline
    edit form on each saved card.

    $address  the address being edited, or null when adding
    $types    Address::TYPES

    Old input is only replayed into the form it came from: an edit that failed
    validation must not refill the add form with the same values. The hidden
    address_id says which form was submitted; the controller ignores it.
--}}
@php
    $editing = isset($address) && $address?->exists;
    $formId = $editing ? (string) $address->id : 'new';
    $mine = (string) old('address_id', 'new') === $formId;

    /** Replay old input for this form only; otherwise fall back to the record. */
    $value = fn (string $field, $fallback = null) => $mine
        ? old($field, $fallback)
        : $fallback;

    $err = fn (string $field) => $mine && $errors->has($field);
@endphp

<input type="hidden" name="address_id" value="{{ $formId }}">

<div class="row g-3">
    <div class="col-6">
        <label for="type-{{ $formId }}" class="form-label">Type</label>
        <select id="type-{{ $formId }}" name="type" class="form-select @if ($err('type')) is-invalid @endif">
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($value('type', $editing ? $address->type : 'home') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($err('type')) <div class="invalid-feedback">{{ $errors->first('type') }}</div> @endif
    </div>

    <div class="col-6">
        <label for="label-{{ $formId }}" class="form-label">Label</label>
        <input type="text" id="label-{{ $formId }}" name="label" maxlength="60"
               value="{{ $value('label', $editing ? $address->label : null) }}"
               class="form-control @if ($err('label')) is-invalid @endif"
               placeholder="Mum's place">
        @if ($err('label')) <div class="invalid-feedback">{{ $errors->first('label') }}</div> @endif
    </div>

    @foreach ([
        ['name', 'Full name', 'col-6', true],
        ['phone', 'Mobile', 'col-6', true],
        ['alternate_phone', 'Alternate mobile', 'col-6', false],
        ['landmark', 'Landmark', 'col-6', false],
        ['line1', 'Flat / house / building', 'col-12', true],
        ['line2', 'Area / street', 'col-12', false],
        ['pincode', 'Pincode', 'col-4', true],
        ['city', 'City', 'col-4', true],
        ['state', 'State', 'col-4', true],
    ] as [$field, $label, $col, $required])
        <div class="{{ $col }}">
            <label for="{{ $field }}-{{ $formId }}" class="form-label">
                {{ $label }}@if ($required)<span class="text-danger"> *</span>@endif
            </label>

            @if ($field === 'state')
                {{-- Picked from a list: the saved state decides the GST on
                     any invoice raised against an order sent here. --}}
                @include('front.partials.state-select', [
                    'id' => 'state-'.$formId,
                    'name' => 'state',
                    'selected' => $value('state', $editing ? $address->state : null),
                    'invalid' => $err('state'),
                    'required' => true,
                ])
            @else
                <input type="text" id="{{ $field }}-{{ $formId }}" name="{{ $field }}"
                       value="{{ $value($field, $editing ? $address->{$field} : null) }}"
                       class="form-control @if ($err($field)) is-invalid @endif"
                       @if ($required) required @endif
                       @if ($field === 'pincode') maxlength="6" inputmode="numeric" @endif
                       @if (str_contains($field, 'phone')) inputmode="tel" @endif>
            @endif

            @if ($err($field)) <div class="invalid-feedback d-block">{{ $errors->first($field) }}</div> @endif
        </div>
    @endforeach

    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_default_shipping" value="1"
                   id="default-{{ $formId }}"
                   @checked($value('is_default_shipping', $editing ? $address->is_default_shipping : false))>
            <label class="form-check-label small" for="default-{{ $formId }}">
                Use as my default address
            </label>
        </div>
    </div>
</div>
