@extends('front.layouts.app')

@section('title', 'My Account')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>My Account</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Account</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-3">@include('front.account._nav')</div>

            <div class="col-lg-9">
                <div class="fc-panel mb-3">
                    <h2 class="fc-panel__title">Hello, {{ Str::before($customer->name, ' ') }}</h2>
                    <p class="text-muted mb-0">
                        Signed in as {{ $customer->email }}.
                        @if ($customer->last_login_at)
                            Last signed in {{ $customer->last_login_at->diffForHumans() }}.
                        @endif
                    </p>
                </div>

                <div class="row g-3 row-cols-1 row-cols-sm-3 mb-3">
                    @foreach ([
                        ['label' => 'Orders', 'value' => $orderCount, 'icon' => 'bi-bag-check', 'route' => 'account.orders'],
                        ['label' => 'Saved addresses', 'value' => $addressCount, 'icon' => 'bi-geo-alt', 'route' => 'account.addresses'],
                        ['label' => 'Wishlist items', 'value' => $wishlistCount, 'icon' => 'bi-heart', 'route' => 'wishlist.index'],
                    ] as $stat)
                        <div class="col">
                            <a href="{{ route($stat['route']) }}" class="fc-account-stat">
                                <i class="bi {{ $stat['icon'] }}"></i>
                                <strong>{{ $stat['value'] }}</strong>
                                <span>{{ $stat['label'] }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="fc-panel">
                    <h2 class="fc-panel__title">Recent orders</h2>

                    @if ($recentOrders->isEmpty())
                        <p class="text-muted mb-0">You have not placed an order yet.
                            <a href="{{ route('products') }}">Start shopping</a>.</p>
                    @else
                        @foreach ($recentOrders as $order)
                            @include('front.account._order-row', ['order' => $order])
                        @endforeach

                        <a href="{{ route('account.orders') }}" class="btn btn-sm btn-outline-secondary mt-2">
                            View all orders
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
