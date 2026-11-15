{{--
    The state picker, shared by checkout and the address book.

    $id, $name, $selected, $invalid, $required

    The caller says whether to mark it invalid: the address book shows two of
    these at once and replays errors only into the form they came from, so
    the partial must not decide that for itself.

    Why a list rather than a text box: the GST on the invoice is worked out
    from this answer. A hand-typed "UP" cannot be matched to Uttar Pradesh
    with certainty, and guessing wrong puts CGST + SGST on an inter-state
    sale — the wrong tax on a legal document.

    An address saved before this list existed may hold something that is not
    on it. That value is kept as its own option rather than silently reset,
    so editing an old address does not quietly move the delivery.
--}}
@php
    $states = indian_states();
    $selected = (string) ($selected ?? '');
    $isKnown = $selected === '' || array_key_exists($selected, $states);
@endphp

<select id="{{ $id }}"
        name="{{ $name }}"
        class="form-select @if ($invalid ?? false) is-invalid @endif"
        @if ($required ?? false) required @endif>
    <option value="">Choose a state</option>

    @unless ($isKnown)
        <option value="{{ $selected }}" selected>{{ $selected }} (not a listed state)</option>
    @endunless

    @foreach ($states as $state => $code)
        <option value="{{ $state }}" @selected($selected === $state)>{{ $state }}</option>
    @endforeach
</select>
