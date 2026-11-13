@extends('admin.layouts.app')

@section('title', 'Staff Users')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Staff Users</li>
@endsection

@section('content')

    <x-admin.card title="Staff list" subtitle="{{ $users->total() }} staff account(s)" icon="bi-people" :flush="true">
        <x-slot:actions>
            @can('users.create')
                <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Staff
                </a>
            @endcan
        </x-slot:actions>

        <form method="GET" class="row g-2 p-3 border-bottom bg-light">
            <div class="col-md-5">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="Search by name, email or phone">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <x-admin.datatable :headers="['Staff', 'Contact', 'Role', 'Status', 'Last login', ['label' => 'Actions', 'end' => true]]"
                           :rows="$users" empty="No staff match this filter." empty-icon="bi-people">
            @foreach ($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ad-avatar">
                                @if ($user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="">
                                @else
                                    {{ $user->initials }}
                                @endif
                            </span>
                            <div class="lh-sm">
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <div class="text-muted" style="font-size:.74rem">{{ $user->designation ?: '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="lh-sm">
                        <div>{{ $user->email }}</div>
                        <div class="text-muted" style="font-size:.74rem">{{ $user->phone ?: '—' }}</div>
                    </td>
                    <td>
                        @forelse ($user->roles as $role)
                            <span class="badge text-bg-light border">{{ $role->name }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td>
                        <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $user->is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </td>
                    <td class="text-muted text-nowrap" style="font-size:.8rem">
                        {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                    </td>
                    <td class="text-end text-nowrap">
                        @can('users.edit')
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary"
                               data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>

                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="d-inline"
                                  data-confirm="{{ $user->is_active ? 'Disable' : 'Enable' }} {{ $user->name }}?">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-{{ $user->is_active ? 'warning' : 'success' }}"
                                        data-bs-toggle="tooltip" title="{{ $user->is_active ? 'Disable' : 'Enable' }}">
                                    <i class="bi {{ $user->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                </button>
                            </form>
                        @endcan

                        @can('users.delete')
                            @if ($user->id !== auth('admin')->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                                      data-confirm="Remove {{ $user->name }}? The account is archived, not erased.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-admin.datatable>
    </x-admin.card>

@endsection
