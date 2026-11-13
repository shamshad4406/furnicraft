@extends('front.layouts.app')

@section('title', 'Forgot Password')

@section('content')
    <section class="fc-section">
        <div class="container-fc">
            <div class="fc-auth-card">
                <h1 class="h4 mb-1" style="color:var(--fc-navy)">Forgot your password?</h1>
                <p class="text-muted small mb-4">Enter your email address and we will send you a reset link.</p>

                <form method="POST" action="{{ route('customer.password.email') }}" novalidate>
                    @csrf
                    <div class="mb-4">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-navy w-100">Send Reset Link</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    <a href="{{ route('customer.login') }}" style="color:var(--fc-navy)">Back to sign in</a>
                </p>
            </div>
        </div>
    </section>
@endsection
