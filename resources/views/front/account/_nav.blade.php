<nav class="fc-account-nav">
    @foreach ([
        ['route' => 'account.dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Overview'],
        ['route' => 'account.orders', 'icon' => 'bi-bag-check', 'label' => 'My Orders'],
        ['route' => 'account.addresses', 'icon' => 'bi-geo-alt', 'label' => 'Addresses'],
        ['route' => 'wishlist.index', 'icon' => 'bi-heart', 'label' => 'Wishlist'],
        ['route' => 'account.profile', 'icon' => 'bi-person', 'label' => 'Profile'],
    ] as $link)
        <a href="{{ route($link['route']) }}"
           class="fc-account-nav__item {{ request()->routeIs($link['route']) || request()->routeIs($link['route'].'.*') ? 'is-active' : '' }}">
            <i class="bi {{ $link['icon'] }}"></i> {{ $link['label'] }}
        </a>
    @endforeach

    <form method="POST" action="{{ route('customer.logout') }}">
        @csrf
        <button type="submit" class="fc-account-nav__item is-signout">
            <i class="bi bi-box-arrow-right"></i> Sign out
        </button>
    </form>
</nav>
