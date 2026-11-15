@php
    // Cached so every page render does not hit the database for the menu.
    $navCategories = cache()->remember('front.nav.categories', now()->addMinutes(10), function () {
        return \App\Models\Category::query()->active()->roots()->ordered()->limit(8)->get(['id', 'name', 'slug']);
    });
@endphp

<header class="fc-header">
    <div class="container-fc">
        <div class="fc-header__inner">

            <a href="{{ route('home') }}" class="fc-logo">
                <img src="{{ setting_file('company_logo') ?? asset('images/logo.svg') }}" alt="{{ settings('company_name', 'FurniCraft') }}">
                <span>
                    <span class="fc-logo__name d-block">Furni<span>Craft</span></span>
                    <span class="fc-logo__tag d-block">{{ settings('company_tagline', 'Style • Comfort • Durability') }}</span>
                </span>
            </a>

            <button class="btn btn-light d-lg-none ms-auto" type="button" data-bs-toggle="collapse"
                    data-bs-target="#fcNav" aria-controls="fcNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="collapse d-lg-flex flex-grow-1 align-items-center" id="fcNav">
                <nav class="fc-nav">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>

                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('categories', 'category.show') ? 'active' : '' }}"
                           href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Categories</a>
                        <ul class="dropdown-menu">
                            @forelse ($navCategories as $category)
                                <li><a class="dropdown-item" href="{{ route('category.show', $category->slug) }}">{{ $category->name }}</a></li>
                            @empty
                                <li><span class="dropdown-item text-muted">No categories yet</span></li>
                            @endforelse
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item fw-semibold" href="{{ route('categories') }}">View all categories</a></li>
                        </ul>
                    </div>

                    <a class="nav-link {{ request()->routeIs('products') ? 'active' : '' }}"
                       href="{{ route('products') }}">Products</a>
                    <a class="nav-link {{ request()->routeIs('page') && request()->route('slug') === 'about-us' ? 'active' : '' }}"
                       href="{{ route('page', 'about-us') }}">About Us</a>
                    <a class="nav-link {{ request()->routeIs('page') && request()->route('slug') === 'contact-us' ? 'active' : '' }}"
                       href="{{ route('page', 'contact-us') }}">Contact Us</a>
                </nav>

                <form class="fc-search ms-lg-3" role="search" action="{{ route('products') }}" method="GET"
                      data-suggest-url="{{ route('search.suggest') }}" autocomplete="off">
                    <input type="search" name="q" class="form-control" placeholder="Search products..."
                           value="{{ request('q') }}" aria-label="Search products">
                    <button type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
                </form>

                @php
                    $cartCount = app(\App\Services\CartService::class)->current(request(), false)?->item_count ?? 0;
                @endphp

                <div class="fc-header__actions ms-lg-3 mt-2 mt-lg-0">
                    <a href="{{ route('wishlist.index') }}" class="fc-icon-link" aria-label="Wishlist">
                        <i class="bi bi-heart"></i>
                        <span class="d-lg-none">Wishlist</span>
                    </a>

                    <a href="{{ route('cart.index') }}" class="fc-icon-link" aria-label="Cart">
                        <i class="bi bi-cart3"></i>
                        <span class="d-lg-none">Cart</span>
                        @if ($cartCount > 0)
                            <span class="fc-icon-link__badge">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('enquiry.create') }}" class="btn btn-gold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-envelope-fill"></i> Enquiry
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
