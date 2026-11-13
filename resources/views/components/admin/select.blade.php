@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'col' => 'col-md-6',
])

@php
    $id = $attributes->get('id', $name);
    $selected = old($name, $value);

    // A plain list (["Admin", "Manager"]) uses its values as option values.
    // A map (["1" => "Sofa"], including pluck('name', 'id') which yields
    // integer keys) uses its KEYS, so ids are submitted rather than labels.
    $optionsAreList = array_is_list(is_array($options) ? $options : $options->all());
@endphp

<div class="{{ $col }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif

    <select id="{{ $id }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->merge(['class' => 'form-select'.($errors->has($name) ? ' is-invalid' : '')]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $key => $text)
            @php $optionValue = $optionsAreList ? $text : $key; @endphp
            <option value="{{ $optionValue }}"
                @selected((string) $optionValue === (string) $selected)>{{ $text }}</option>
        @endforeach

        {{-- Slot lets a caller supply grouped or indented options instead. --}}
        {{ $slot }}
    </select>

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
