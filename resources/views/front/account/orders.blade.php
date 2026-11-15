@extends('front.layouts.app')

@section('title', 'My Orders')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>My Orders</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('account.dashboard') }}">Account</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-3">@include('front.account._nav')</div>

            <div class="col-lg-9">
                <form method="GET" class="fc-chips mb-3">
                    <a href="{{ route('account.orders') }}" class="fc-chip {{ request('status') ? '' : 'is-active' }}">All</a>
                    @foreach (['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $status)
                        <a href="{{ route('account.orders', ['status' => $status]) }}"
                           class="fc-chip {{ request('status') === $status ? 'is-active' : '' }}">
                            {{ $statuses[$status] }}
                        </a>
                    @endforeach
                </form>

                @if ($orders->isEmpty())
                    <div class="fc-empty-state">
                        <i class="bi bi-bag"></i>
                        <h2>No orders here</h2>
                        <p>{{ request('status') ? 'No orders with this status.' : 'You have not placed an order yet.' }}</p>
                        <a href="{{ route('products') }}" class="btn btn-navy btn-sm">Start shopping</a>
                    </div>
                @else
                    <div class="fc-panel">
                        @foreach ($orders as $order)
                            @include('front.account._order-row', ['order' => $order])
                        @endforeach
                    </div>

                    @if ($orders->hasPages())
                        <div class="fc-pagination">{{ $orders->links('pagination::bootstrap-5') }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</section>

@endsection
