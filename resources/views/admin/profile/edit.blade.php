@extends('admin.layouts.app')

@section('title', 'My Profile')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            <x-admin.card title="Profile details" icon="bi-person">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="ad-avatar" style="width:64px;height:64px;font-size:1.15rem">
                        @if ($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="" id="profilePreview">
                        @else
                            {{ $user->initials }}
                        @endif
                    </span>
                    <div class="flex-grow-1">
                        <label for="avatar" class="form-label">Profile photo</label>
                        <input type="file" id="avatar" name="avatar" accept="image/*" data-preview="#profilePreview"
                               class="form-control form-control-sm @error('avatar') is-invalid @enderror">
                        @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3">
                    <x-admin.input name="name" label="Full name" :value="$user->name" required />
                    <x-admin.input name="designation" label="Designation" :value="$user->designation" />
                    <x-admin.input name="email" label="Email address" type="email" :value="$user->email" required />
                    <x-admin.input name="phone" label="Mobile number" type="tel" :value="$user->phone" />
                </div>

                <x-slot:footer>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Profile</button>
                </x-slot:footer>
            </x-admin.card>
        </form>
    </div>

    <div class="col-lg-5">
        <form method="POST" action="{{ route('admin.profile.password') }}" novalidate>
            @csrf
            @method('PUT')

            <x-admin.card title="Change password" icon="bi-key">
                <div class="row g-3">
                    <x-admin.input name="current_password" label="Current password" type="password"
                                   col="col-12" required autocomplete="current-password" />
                    <x-admin.input name="password" label="New password" type="password" col="col-12" required
                                   autocomplete="new-password" help="At least 8 characters, with letters and numbers." />
                    <x-admin.input name="password_confirmation" label="Confirm new password" type="password"
                                   col="col-12" required autocomplete="new-password" />
                </div>

                <x-slot:footer>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i> Update Password</button>
                </x-slot:footer>
            </x-admin.card>
        </form>

        <x-admin.card title="Account" icon="bi-info-circle" class="mt-3">
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted fw-normal">Role</dt>
                <dd class="col-7 fw-semibold">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Permissions</dt>
                <dd class="col-7 fw-semibold">{{ $user->getAllPermissions()->count() }}</dd>

                <dt class="col-5 text-muted fw-normal">Last login</dt>
                <dd class="col-7 fw-semibold">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</dd>

                <dt class="col-5 text-muted fw-normal">Last login IP</dt>
                <dd class="col-7 fw-semibold">{{ $user->last_login_ip ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal">Member since</dt>
                <dd class="col-7 fw-semibold mb-0">{{ $user->created_at->format('d M Y') }}</dd>
            </dl>
        </x-admin.card>
    </div>
</div>

@endsection
