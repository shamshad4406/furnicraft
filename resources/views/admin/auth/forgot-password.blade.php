@extends('admin.layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h2 class="h4 fw-bold mb-1" style="color:var(--fc-navy)">Forgot password</h2>
    <p class="text-muted small mb-4">We will email you a link to choose a new password.</p>

    <form method="POST" action="{{ route('admin.password.email') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" required autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Send Reset Link</button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <a href="{{ route('admin.login') }}" style="color:var(--fc-navy)"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a>
    </p>
@endsection
