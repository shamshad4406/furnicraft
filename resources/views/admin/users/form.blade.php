@extends('admin.layouts.app')

@php $editing = $user->exists; @endphp

@section('title', $editing ? 'Edit Staff' : 'Add Staff')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Staff Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $user->name : 'Add' }}</li>
@endsection

@section('content')

<form method="POST"
      action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
      enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Staff details" icon="bi-person-badge">
                <div class="row g-3">
                    <x-admin.input name="name" label="Full name" :value="$user->name" required />
                    <x-admin.input name="designation" label="Designation" :value="$user->designation"
                                   help="Shown on the staff list, e.g. Store Manager." />
                    <x-admin.input name="email" label="Email address" type="email" :value="$user->email" required />
                    <x-admin.input name="phone" label="Mobile number" type="tel" :value="$user->phone" />
                </div>
            </x-admin.card>

            <x-admin.card title="{{ $editing ? 'Change password' : 'Password' }}" icon="bi-key" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="password" label="Password" type="password" :required="! $editing"
                                   autocomplete="new-password"
                                   :help="$editing ? 'Leave blank to keep the current password.' : 'At least 8 characters, with letters and numbers.'" />
                    <x-admin.input name="password_confirmation" label="Confirm password" type="password"
                                   :required="! $editing" autocomplete="new-password" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Role & access" icon="bi-shield-lock">
                <div class="row g-3">
                    <x-admin.select name="role" label="Role" col="col-12" required
                                    :options="$roles->all()"
                                    :value="$user->roles->first()?->name"
                                    placeholder="Select a role"
                                    help="The role decides which menu items and pages this user can open." />

                    <x-admin.switcher name="is_active" label="Status" col="col-12" :checked="$user->is_active">
                        Account is active
                    </x-admin.switcher>
                </div>
            </x-admin.card>

            <x-admin.card title="Profile photo" icon="bi-image" class="mt-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="ad-avatar" style="width:56px;height:56px;font-size:1rem">
                        @if ($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="" id="avatarPreview">
                        @else
                            {{ $editing ? $user->initials : '?' }}
                        @endif
                    </span>
                    <div class="flex-grow-1">
                        <input type="file" name="avatar" accept="image/*" data-preview="#avatarPreview"
                               class="form-control form-control-sm @error('avatar') is-invalid @enderror">
                        @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">JPG, PNG or WebP, up to 2 MB.</div>
                    </div>
                </div>
            </x-admin.card>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Changes' : 'Create Staff' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
