@props([
    'name',
    'label' => null,
    'value' => null,
    'help' => null,
    'rows' => 3,
    'required' => false,
    'col' => 'col-12',
])

@php $id = $attributes->get('id', $name); @endphp

<div class="{{ $col }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif

    <textarea id="{{ $id }}"
              name="{{ $name }}"
              rows="{{ $rows }}"
              @if ($required) required @endif
              {{ $attributes->except('id')->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
