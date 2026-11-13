@extends('admin.layouts.app')

@section('title', 'Login History')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.activity.index') }}">Activity Log</a></li>
    <li class="breadcrumb-item active" aria-current="page">Login History</li>
@endsection

@section('content')

<x-admin.card title="Login attempts" subtitle="{{ $logins->total() }} record(s) — successful and failed"
              icon="bi-box-arrow-in-right" :flush="true">

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search by email">
        </div>
        <div class="col-md-3">
            <select name="guard" class="form-select form-select-sm">
                <option value="">All panels</option>
                <option value="admin" @selected(request('guard') === 'admin')>Admin</option>
                <option value="customer" @selected(request('guard') === 'customer')>Customer</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All results</option>
                <option value="success" @selected(request('status') === 'success')>Successful</option>
                <option value="failed" @selected(request('status') === 'failed')>Failed</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
            <a href="{{ route('admin.activity.logins') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['When', 'Account', 'Panel', 'Result', 'IP address', 'Signed out']"
                       :rows="$logins" empty="No login attempts recorded." empty-icon="bi-person-x">
        @foreach ($logins as $login)
            <tr>
                <td class="text-nowrap text-muted" style="font-size:.78rem">{{ $login->created_at->format('d M Y, H:i') }}</td>
                <td>
                    <div class="fw-semibold">{{ $login->authenticatable?->name ?? ($login->email ?: 'Unknown') }}</div>
                    @if ($login->authenticatable)
                        <div class="text-muted" style="font-size:.74rem">{{ $login->email }}</div>
                    @endif
                </td>
                <td><span class="badge text-bg-light border text-capitalize">{{ $login->guard }}</span></td>
                <td>
                    <span class="badge {{ $login->successful ? 'text-bg-success' : 'text-bg-danger' }}">
                        {{ $login->successful ? 'Success' : 'Failed' }}
                    </span>
                    @if ($login->reason)
                        <div class="text-muted" style="font-size:.72rem">{{ $login->reason }}</div>
                    @endif
                </td>
                <td class="text-muted" style="font-size:.8rem">{{ $login->ip_address ?: '—' }}</td>
                <td class="text-muted" style="font-size:.8rem">{{ $login->logged_out_at?->format('d M, H:i') ?? '—' }}</td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
