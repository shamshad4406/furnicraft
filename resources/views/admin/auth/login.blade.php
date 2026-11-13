@extends('admin.layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2 class="h4 fw-bold mb-1" style="color:var(--fc-navy)">Sign in</h2>
    <p class="text-muted small mb-4">Enter your staff credentials to continue.</p>

    <form method="POST" action="{{ route('admin.login.attempt') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       required autofocus autocomplete="username" placeholder="you@furnicraft.in">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                <input id="password" name="password" type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password" placeholder="••••••••">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <a href="{{ route('admin.password.request') }}" class="small fw-semibold" style="color:var(--fc-navy)">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
    </form>

    @if (app()->environment('local'))
        <div class="alert alert-light border mt-4 mb-0 small">
            <strong class="d-block mb-1">Seeded demo accounts</strong>
            admin@furnicraft.in · manager@furnicraft.in · sales@furnicraft.in ·
            inventory@furnicraft.in · delivery@furnicraft.in<br>
            <span class="text-muted">Password for all: <code>Furni@2026</code> — change before staging.</span>
        </div>
    @endif
@endsection
