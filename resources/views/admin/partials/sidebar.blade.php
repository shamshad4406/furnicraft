@php
    /**
     * Menu is driven by permissions, so each role sees only its own items.
     * Modules scheduled for later phases are shown disabled with a phase badge
     * so the owner can see the roadmap inside the panel.
     */
    $groups = [
        'Main' => [
            ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'admin.dashboard', 'can' => 'dashboard.view'],
        ],
        'Catalog' => [
            ['label' => 'Categories', 'icon' => 'bi-diagram-3', 'route' => 'admin.categories.index', 'can' => 'categories.view', 'active' => 'admin.categories.*'],
            ['label' => 'Brands', 'icon' => 'bi-award', 'route' => 'admin.brands.index', 'can' => 'brands.view', 'active' => 'admin.brands.*'],
            ['label' => 'Products', 'icon' => 'bi-box-seam', 'route' => 'admin.products.index', 'can' => 'products.view', 'active' => 'admin.products.*'],
        ],
        'Content & Delivery' => [
            ['label' => 'Pages', 'icon' => 'bi-file-earmark-richtext', 'route' => 'admin.cms.index', 'can' => 'cms.view', 'active' => 'admin.cms.*'],
            ['label' => 'Pincodes', 'icon' => 'bi-geo-alt', 'route' => 'admin.delivery.pincodes.index', 'can' => 'shipments.view', 'active' => 'admin.delivery.pincodes.*'],
            ['label' => 'Delivery Zones', 'icon' => 'bi-map', 'route' => 'admin.delivery.zones.index', 'can' => 'shipments.view', 'active' => 'admin.delivery.zones.*'],
        ],
        'Sales' => [
            ['label' => 'Coupons', 'icon' => 'bi-ticket-perforated', 'route' => 'admin.coupons.index', 'can' => 'coupons.view', 'active' => 'admin.coupons.*'],
            ['label' => 'Orders', 'icon' => 'bi-bag-check', 'route' => 'admin.orders.index', 'can' => 'orders.view', 'active' => 'admin.orders.*'],
            ['label' => 'Shipments', 'icon' => 'bi-truck', 'route' => 'admin.shipments.index', 'can' => 'shipments.view', 'active' => 'admin.shipments.*'],
            ['label' => 'Returns', 'icon' => 'bi-arrow-return-left', 'route' => 'admin.returns.index', 'can' => 'orders.view', 'active' => 'admin.returns.*'],
            ['label' => 'Enquiries', 'icon' => 'bi-chat-left-text', 'route' => 'admin.enquiries.index', 'can' => 'enquiries.view', 'active' => 'admin.enquiries.*'],
            ['label' => 'Quotations', 'icon' => 'bi-file-earmark-text', 'route' => 'admin.quotations.index', 'can' => 'quotations.view', 'active' => 'admin.quotations.*'],
            ['label' => 'Customers', 'icon' => 'bi-person-heart', 'route' => 'admin.customers.index', 'can' => 'customers.view', 'active' => 'admin.customers.*'],
        ],
        'Stock & Reports' => [
            ['label' => 'Inventory', 'icon' => 'bi-boxes', 'route' => 'admin.inventory.index', 'can' => 'inventory.view', 'active' => 'admin.inventory.*'],
            ['label' => 'Suppliers', 'icon' => 'bi-truck-front', 'route' => 'admin.suppliers.index', 'can' => 'suppliers.view', 'active' => 'admin.suppliers.*'],
            ['label' => 'Purchases', 'icon' => 'bi-cart-plus', 'route' => 'admin.purchases.index', 'can' => 'purchases.view', 'active' => 'admin.purchases.*'],
            ['label' => 'Reports', 'icon' => 'bi-graph-up', 'route' => 'admin.reports.index', 'can' => 'reports.view', 'active' => 'admin.reports.*'],
        ],
        'Marketing' => [
            ['label' => 'Banners', 'icon' => 'bi-images', 'route' => 'admin.banners.index', 'can' => 'banners.view', 'active' => 'admin.banners.*'],
            ['label' => 'Reviews', 'icon' => 'bi-star', 'route' => 'admin.reviews.index', 'can' => 'reviews.view', 'active' => 'admin.reviews.*'],
            ['label' => 'Newsletter', 'icon' => 'bi-envelope-paper', 'route' => 'admin.newsletter.index', 'can' => 'newsletter.view', 'active' => 'admin.newsletter.*'],
        ],
        'Administration' => [
            ['label' => 'Staff Users', 'icon' => 'bi-people', 'route' => 'admin.users.index', 'can' => 'users.view', 'active' => 'admin.users.*'],
            ['label' => 'Roles & Permissions', 'icon' => 'bi-shield-lock', 'route' => 'admin.roles.index', 'can' => 'roles.view', 'active' => 'admin.roles.*'],
            ['label' => 'Website Settings', 'icon' => 'bi-gear', 'route' => 'admin.settings.index', 'can' => 'settings.view', 'active' => 'admin.settings.*'],
            ['label' => 'Activity Log', 'icon' => 'bi-clock-history', 'route' => 'admin.activity.index', 'can' => 'activity.view'],
            ['label' => 'Login History', 'icon' => 'bi-box-arrow-in-right', 'route' => 'admin.activity.logins', 'can' => 'activity.view'],
        ],
    ];
@endphp

<aside class="ad-sidebar">
    <a href="{{ route('admin.dashboard') }}" class="ad-sidebar__brand">
        <img src="{{ asset('images/logo.svg') }}" alt="">
        <span>
            <span class="name d-block">Furni<span>Craft</span></span>
            <span class="sub d-block">Admin Panel</span>
        </span>
    </a>

    <nav class="ad-sidebar__scroll">
        @foreach ($groups as $heading => $items)
            @php
                $visible = collect($items)->filter(fn ($item) => auth('admin')->user()->can($item['can']));
            @endphp

            @if ($visible->isNotEmpty())
                <div class="ad-sidebar__heading">{{ $heading }}</div>

                @foreach ($visible as $item)
                    @if (isset($item['route']))
                        <a class="nav-link {{ request()->routeIs($item['active'] ?? $item['route']) ? 'active' : '' }}"
                           href="{{ route($item['route']) }}">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @else
                        <span class="nav-link disabled" aria-disabled="true">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                            <span class="badge text-bg-light">P{{ $item['phase'] }}</span>
                        </span>
                    @endif
                @endforeach
            @endif
        @endforeach
    </nav>
</aside>
