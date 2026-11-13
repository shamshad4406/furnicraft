@props([
    'name',
    'label' => null,
    'checked' => false,
    'help' => null,
    'col' => 'col-md-6',
])

@php
    $id = $attributes->get('id', $name);
    $isOn = (bool) old($name, $checked);
@endphp

<div class="{{ $col }}">
    @if ($label)
        <span class="form-label d-block">{{ $label }}</span>
    @endif

    <div class="form-check form-switch">
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch"
               id="{{ $id }}" name="{{ $name }}" value="1" @checked($isOn)>
        <label class="form-check-label small" for="{{ $id }}">{{ $slot->isEmpty() ? 'Enabled' : $slot }}</label>
    </div>

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
