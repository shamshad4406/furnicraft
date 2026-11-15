@php
    /**
     * $filters   — available option values from ProductBrowser::filterOptions()
     * $action    — where the form submits (listing or category page)
     * $lockCategory — true on a category page, where category is fixed by the URL
     */
    $lockCategory = $lockCategory ?? false;
    $active = collect(\App\Services\ProductBrowser::FILTER_KEYS)
        ->filter(fn ($key) => request()->filled($key) && ! ($lockCategory && $key === 'category'));
@endphp

<form method="GET" action="{{ $action }}" class="fc-filters" id="productFilters">
    {{-- Keep sorting when filters are applied. --}}
    @if (request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif
    @if (request('q'))
        <input type="hidden" name="q" value="{{ request('q') }}">
    @endif

    <div class="fc-filters__head">
        <h2><i class="bi bi-funnel"></i> Filters</h2>
        @if ($active->isNotEmpty())
            <a href="{{ $action }}{{ request('sort') ? '?sort='.request('sort') : '' }}" class="fc-filters__clear">
                Clear all
            </a>
        @endif
    </div>

    @unless ($lockCategory)
        @if (! empty($categories) && count($categories))
            <div class="fc-filter-group">
                <h3>Category</h3>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    @endunless

    <div class="fc-filter-group">
        <h3>Price</h3>
        <div class="fc-filter-price">
            <input type="number" name="min_price" class="form-control form-control-sm" min="0"
                   placeholder="{{ money($filters['price']['min']) }}" value="{{ request('min_price') }}"
                   aria-label="Minimum price">
            <span>–</span>
            <input type="number" name="max_price" class="form-control form-control-sm" min="0"
                   placeholder="{{ money($filters['price']['max']) }}" value="{{ request('max_price') }}"
                   aria-label="Maximum price">
        </div>
    </div>

    @if (count($filters['materials']))
        <div class="fc-filter-group">
            <h3>Material</h3>
            <select name="material" class="form-select form-select-sm">
                <option value="">Any material</option>
                @foreach ($filters['materials'] as $material)
                    <option value="{{ $material }}" @selected(request('material') === $material)>{{ $material }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (count($filters['colors']))
        <div class="fc-filter-group">
            <h3>Colour</h3>
            <select name="color" class="form-select form-select-sm">
                <option value="">Any colour</option>
                @foreach ($filters['colors'] as $color)
                    <option value="{{ $color }}" @selected(request('color') === $color)>{{ $color }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (count($filters['sizes']))
        <div class="fc-filter-group">
            <h3>Size</h3>
            <select name="size" class="form-select form-select-sm">
                <option value="">Any size</option>
                @foreach ($filters['sizes'] as $size)
                    <option value="{{ $size }}" @selected(request('size') === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="fc-filter-group">
        <h3>Availability</h3>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="filterInStock"
                   @checked(request()->boolean('in_stock'))>
            <label class="form-check-label" for="filterInStock">In stock only</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="discount" value="1" id="filterDiscount"
                   @checked(request()->boolean('discount'))>
            <label class="form-check-label" for="filterDiscount">On offer</label>
        </div>
    </div>

    <div class="fc-filters__group">
        <h3>Customer rating</h3>

        @foreach ([4, 3, 2] as $stars)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="rating" value="{{ $stars }}"
                       id="filterRating{{ $stars }}" @checked((int) request('rating') === $stars)>
                <label class="form-check-label d-flex align-items-center gap-1" for="filterRating{{ $stars }}">
                    <span class="fc-stars fc-stars--sm" role="img" aria-label="{{ $stars }} stars and up">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="bi bi-star{{ $i <= $stars ? '-fill' : '' }}"></i>
                        @endfor
                    </span>
                    <span class="small text-muted">&amp; up</span>
                </label>
            </div>
        @endforeach

        @if (request()->filled('rating'))
            <div class="form-check">
                <input class="form-check-input" type="radio" name="rating" value="" id="filterRatingAny" >
                <label class="form-check-label small text-muted" for="filterRatingAny">Any rating</label>
            </div>
        @endif
    </div>

    <button type="submit" class="btn btn-navy w-100">Apply filters</button>
</form>
