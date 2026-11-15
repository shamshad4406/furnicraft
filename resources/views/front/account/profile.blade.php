@extends('front.layouts.app')

@section('title', 'My Profile')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>My Profile</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('account.dashboard') }}">Account</a></li>
                <li class="breadcrumb-item active" aria-current="page">Profile</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-3">@include('front.account._nav')</div>

            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-xl-7">
                        {{-- h-100: details and password sit side by side. --}}
                        <div class="fc-panel h-100">
                            <h2 class="fc-panel__title">Your details</h2>

                            <form method="POST" action="{{ route('account.profile.update') }}" novalidate>
                                @csrf @method('PUT')

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="name" class="form-label">Full name</label>
                                        <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}"
                                               class="form-control @error('name') is-invalid @enderror" required>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}"
                                               class="form-control @error('email') is-invalid @enderror" required>
                                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <label for="phone" class="form-label">Mobile</label>
                                        <input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                                               class="form-control @error('phone') is-invalid @enderror" required>
                                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-navy mt-3">Save details</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="fc-panel h-100">
                            <h2 class="fc-panel__title">Change password</h2>

                            <form method="POST" action="{{ route('account.password.update') }}" novalidate>
                                @csrf @method('PUT')

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="current_password" class="form-label">Current password</label>
                                        <input type="password" id="current_password" name="current_password"
                                               class="form-control @error('current_password') is-invalid @enderror"
                                               autocomplete="current-password" required>
                                        @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <label for="password" class="form-label">New password</label>
                                        <input type="password" id="password" name="password"
                                               class="form-control @error('password') is-invalid @enderror"
                                               autocomplete="new-password" required>
                                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">At least 8 characters, with letters and numbers.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                                        <input type="password" id="password_confirmation" name="password_confirmation"
                                               class="form-control" autocomplete="new-password" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-navy mt-3">Change password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
