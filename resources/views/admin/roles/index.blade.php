@extends('admin.layouts.app')

@section('title', 'Roles & Permissions')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Roles &amp; Permissions</li>
@endsection

@section('content')

    <x-admin.card title="Roles" subtitle="A role decides which menu items and pages its staff can open."
                  icon="bi-shield-lock" :flush="true">
        <x-slot:actions>
            @can('roles.create')
                <a href="{{ route('admin.roles.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Role
                </a>
            @endcan
        </x-slot:actions>

        <x-admin.datatable :headers="['Role', 'Permissions', 'Staff', ['label' => 'Actions', 'end' => true]]"
                           empty="No roles found.">
            @foreach ($roles as $role)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $role->name }}</span>
                        @if (in_array($role->name, $systemRoles, true))
                            <span class="badge text-bg-light border ms-1" data-bs-toggle="tooltip"
                                  title="Created by the seeder — cannot be renamed or deleted">System</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge text-bg-light border">{{ $role->permissions_count }} permission(s)</span>
                        @if ($role->name === 'Admin')
                            <span class="text-muted ms-1" style="font-size:.76rem">full access</span>
                        @endif
                    </td>
                    <td>{{ $role->users_count }}</td>
                    <td class="text-end text-nowrap">
                        @can('roles.edit')
                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i>{{ $role->name === 'Admin' ? 'View' : 'Edit' }}
                            </a>
                        @endcan
                        @can('roles.delete')
                            @unless (in_array($role->name, $systemRoles, true))
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline"
                                      data-confirm="Delete the role {{ $role->name }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-admin.datatable>
    </x-admin.card>

@endsection
