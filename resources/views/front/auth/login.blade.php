@extends('front.layouts.app')

@section('title', 'Customer Login')

@section('content')
    <section class="fc-section">
        <div class="container-fc">
            <div class="fc-auth-card">
                <h1 class="h4 mb-1" style="color:var(--fc-navy)">Welcome back</h1>
                <p class="text-muted small mb-4">Sign in to your {{ settings('company_name', 'FurniCraft') }} account.</p>

                <form method="POST" action="{{ route('customer.login') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password"
                               class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                            <label class="form-check-label small" for="remember">Remember me</label>
                        </div>
                        <a href="{{ route('customer.password.request') }}" class="small" style="color:var(--fc-navy)">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-navy w-100">Sign In</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    New here? <a href="{{ route('customer.register') }}" style="color:var(--fc-navy);font-weight:600">Create an account</a>
                </p>
            </div>
        </div>
    </section>
@endsection
