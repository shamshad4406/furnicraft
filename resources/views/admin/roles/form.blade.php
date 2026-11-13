@extends('admin.layouts.app')

@php
    $editing = $role->exists;
    $locked = $editing && $role->name === 'Admin';
    $isSystem = $editing && in_array($role->name, $systemRoles, true);
@endphp

@section('title', $editing ? 'Edit Role — '.$role->name : 'Add Role')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles &amp; Permissions</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $role->name : 'Add' }}</li>
@endsection

@section('content')

@if ($locked)
    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-info-circle-fill"></i>
        <div>The <strong>Admin</strong> role always holds every permission, so it is shown read-only.</div>
    </div>
@endif

<form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <x-admin.card title="Role details" icon="bi-shield-lock">
        <div class="row g-3">
            <x-admin.input name="name" label="Role name" :value="$role->name" required
                           :disabled="$isSystem"
                           :help="$isSystem ? 'System role names are fixed.' : 'For example: Showroom Staff.'" />
            @if ($isSystem)
                <input type="hidden" name="name" value="{{ $role->name }}">
            @endif
        </div>
    </x-admin.card>

    <x-admin.card title="Permissions" subtitle="Tick what this role may do. Modules for later phases are listed now so roles do not need editing again."
                  icon="bi-ui-checks" class="mt-3">
        <fieldset @disabled($locked) class="border-0 p-0 m-0">
            <div class="ad-perm-grid">
                @foreach ($grouped as $module => $permissions)
                    <div class="ad-perm-module">
                        <div class="ad-perm-module__title">
                            <input class="form-check-input mt-0" type="checkbox" data-check-module
                                   id="mod-{{ $module }}" aria-label="Select all {{ $module }} permissions">
                            <label for="mod-{{ $module }}" class="mb-0">{{ str_replace('_', ' ', $module) }}</label>
                        </div>

                        @foreach ($permissions as $name => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]"
                                       value="{{ $name }}" id="perm-{{ $name }}"
                                       @checked($locked || in_array($name, old('permissions', $assigned), true))>
                                <label class="form-check-label small" for="perm-{{ $name }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </fieldset>

        <x-slot:footer>
            <div class="d-flex gap-2">
                @unless ($locked)
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Permissions' : 'Create Role' }}
                    </button>
                @endunless
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Back to roles</a>
            </div>
        </x-slot:footer>
    </x-admin.card>
</form>

@endsection
