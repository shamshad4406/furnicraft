@extends('front.layouts.app')

@section('title', 'Create Account')

@section('content')
    <section class="fc-section">
        <div class="container-fc">
            <div class="fc-auth-card">
                <h1 class="h4 mb-1" style="color:var(--fc-navy)">Create your account</h1>
                <p class="text-muted small mb-4">Mobile and email OTP verification is added in Phase 4.</p>

                <form method="POST" action="{{ route('customer.register') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror" required autofocus>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Mobile number</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                               class="form-control @error('phone') is-invalid @enderror" required autocomplete="tel">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" name="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   class="form-control" required autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-navy w-100">Create Account</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    Already registered? <a href="{{ route('customer.login') }}" style="color:var(--fc-navy);font-weight:600">Sign in</a>
                </p>
            </div>
        </div>
    </section>
@endsection
