@extends('front.layouts.app')

@section('title', 'Reset Password')

@section('content')
    <section class="fc-section">
        <div class="container-fc">
            <div class="fc-auth-card">
                <h1 class="h4 mb-4" style="color:var(--fc-navy)">Choose a new password</h1>

                <form method="POST" action="{{ route('customer.password.update') }}" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $email) }}"
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input id="password" name="password" type="password"
                               class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">At least 8 characters, with letters and numbers.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               class="form-control" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-navy w-100">Reset Password</button>
                </form>
            </div>
        </div>
    </section>
@endsection
