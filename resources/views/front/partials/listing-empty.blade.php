@php
    $clearUrl = $clearUrl ?? route('products');
@endphp

<div class="fc-empty-state">
    <i class="bi bi-search"></i>
    <h2>Nothing matched</h2>
    <p>
        @if (request('q'))
            No products found for “{{ request('q') }}”. Try a different word, or widen the filters.
        @else
            No products match these filters. Try widening the price range or clearing a filter.
        @endif
    </p>
    <a href="{{ $clearUrl }}" class="btn btn-navy btn-sm">Clear filters</a>
</div>
