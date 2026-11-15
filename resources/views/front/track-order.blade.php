@extends('front.layouts.app')

@section('title', 'Track your order')
@section('meta_description', 'Check the status of your FurniCraft order using your order number and email address.')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Track your order</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Track order</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-6">
                <div class="fc-panel">
                    <h2 class="fc-panel__title">Find your order</h2>

                    <p class="text-muted small">
                        Enter the order number from your confirmation email, along with the
                        email address you placed the order with.
                    </p>

                    <form method="POST" action="{{ route('track.find') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="order_number" class="form-label">
                                Order number <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="order_number" name="order_number"
                                   value="{{ old('order_number') }}"
                                   class="form-control @error('order_number') is-invalid @enderror"
                                   placeholder="{{ $exampleOrderNumber ?? 'FC/2611/00001' }}" required>
                            @error('order_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email address <span class="text-danger">*</span>
                            </label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="you@example.com" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-navy w-100">
                            <i class="bi bi-search"></i> Check status
                        </button>
                    </form>

                    <hr class="my-4">

                    <p class="small text-muted mb-0">
                        @auth('customer')
                            All your orders are listed in
                            <a href="{{ route('account.orders') }}">My Orders</a>.
                        @else
                            Have an account?
                            <a href="{{ route('customer.login') }}">Sign in</a>
                            to see all your orders in one place.
                        @endauth
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
