{{-- Result count + sort control. Keeps existing query params on sort change. --}}
@php
    // Built in PHP rather than with an inline if/else: a Blade directive that
    // sits immediately after a word character is not compiled at all, so
    // "found" followed straight by the else directive silently breaks.
    $total = $products->total();

    $countLabel = $total === 0
        ? 'No products found'
        : 'Showing '.$products->firstItem().'–'.$products->lastItem().' of '.$total.' '.Str::plural('product', $total);
@endphp

<div class="fc-toolbar">
    <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" data-filters-toggle
            aria-expanded="false" aria-controls="productFilters">
        <i class="bi bi-funnel"></i> Filters
    </button>

    <span class="fc-toolbar__count">{{ $countLabel }}</span>

    <form method="GET" class="fc-toolbar__sort">
        @foreach (request()->except(['sort', 'page']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

        <label for="sort" class="form-label mb-0">Sort by</label>
        {{-- Submitted by app.js; no inline handler, so the CSP stays strict. --}}
        <select id="sort" name="sort" class="form-select form-select-sm" data-autosubmit>
            @foreach ($sorts as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'featured') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-navy">Go</button></noscript>
    </form>
</div>
