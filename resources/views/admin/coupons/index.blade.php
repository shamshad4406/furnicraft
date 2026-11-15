@extends('admin.layouts.app')

@section('title', 'Coupons')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Coupons</li>
@endsection

@section('content')

<x-admin.card title="Coupons" subtitle="{{ $coupons->total() }} coupon(s)" icon="bi-ticket-perforated" :flush="true">
    <x-slot:actions>
        @can('coupons.create')
            <a href="{{ route('admin.coupons.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Coupon
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-7">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search code or description">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All coupons</option>
                <option value="live" @selected(request('status') === 'live')>Live now</option>
                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>

    <x-admin.datatable :headers="['Code', 'Discount', 'Conditions', 'Used', 'Window', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$coupons" empty="No coupons yet." empty-icon="bi-ticket-perforated">
        @foreach ($coupons as $coupon)
            <tr>
                <td>
                    <div class="fw-semibold"><code>{{ $coupon->code }}</code></div>
                    @if ($coupon->description)
                        <div class="text-muted" style="font-size:.74rem">{{ Str::limit($coupon->description, 50) }}</div>
                    @endif
                </td>
                <td>{{ $coupon->summary }}</td>
                <td class="lh-sm" style="font-size:.8rem">
                    @if ($coupon->min_order_value > 0)
                        <div>Min {{ money($coupon->min_order_value) }}</div>
                    @endif
                    @if ($coupon->usage_limit_per_customer)
                        <div class="text-muted">{{ $coupon->usage_limit_per_customer }}× per customer</div>
                    @endif
                    @if (! $coupon->min_order_value && ! $coupon->usage_limit_per_customer)
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    <span class="badge text-bg-light border">
                        {{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}
                    </span>
                </td>
                <td class="lh-sm text-muted" style="font-size:.78rem">
                    <div>{{ $coupon->starts_at?->format('d M Y') ?? 'Any time' }}</div>
                    <div>{{ $coupon->expires_at ? 'to '.$coupon->expires_at->format('d M Y') : 'no expiry' }}</div>
                </td>
                <td>
                    @if (! $coupon->is_active)
                        <span class="badge text-bg-secondary">Inactive</span>
                    @elseif ($coupon->hasExpired())
                        <span class="badge text-bg-danger">Expired</span>
                    @elseif (! $coupon->hasStarted())
                        <span class="badge text-bg-info">Scheduled</span>
                    @elseif ($coupon->isExhausted())
                        <span class="badge text-bg-warning">Fully claimed</span>
                    @else
                        <span class="badge text-bg-success">Live</span>
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.coupons.show', $coupon) }}" class="btn btn-sm btn-outline-secondary"
                       data-bs-toggle="tooltip" title="Usage"><i class="bi bi-graph-up"></i></a>

                    @can('coupons.edit')
                        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-outline-secondary"
                           data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-{{ $coupon->is_active ? 'warning' : 'success' }}"
                                    data-bs-toggle="tooltip" title="{{ $coupon->is_active ? 'Deactivate' : 'Activate' }}">
                                <i class="bi {{ $coupon->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            </button>
                        </form>
                    @endcan

                    @can('coupons.delete')
                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="d-inline"
                              data-confirm="Delete the coupon {{ $coupon->code }}?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
