@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')

    <div class="row g-3 mb-3">
        @forelse ($stats as $stat)
            <div class="col-sm-6 col-xl-3">
                <div class="ad-card">
                    <div class="ad-card__body">
                        <div class="ad-stat ad-stat--{{ $stat['tone'] }}">
                            <div class="ad-stat__icon"><i class="bi {{ $stat['icon'] }}"></i></div>
                            <div>
                                <div class="ad-stat__value">{{ number_format($stat['value']) }}</div>
                                <div class="ad-stat__label">{{ $stat['label'] }}</div>
                                <div class="text-muted" style="font-size:.74rem">{{ $stat['sub'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="ad-card">
                    <div class="ad-card__body d-flex align-items-center gap-3">
                        <i class="bi bi-person-check fs-3" style="color:var(--fc-gold)"></i>
                        <div>
                            <div class="fw-semibold" style="color:var(--fc-navy)">Welcome, {{ auth('admin')->user()->name }}</div>
                            <div class="text-muted small">Your menu on the left shows everything your role can open.</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($canSeeAudit)
    <div class="row g-3">
        <div class="col-xl-7">
            <x-admin.card title="Recent activity" icon="bi-clock-history" :flush="true">
                <x-slot:actions>
                    @can('activity.view')
                        <a href="{{ route('admin.activity.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
                    @endcan
                </x-slot:actions>

                <x-admin.datatable :headers="['Description', 'By', 'When']" :rows="$activities"
                                   empty="No activity recorded yet." empty-icon="bi-clock-history">
                    @foreach ($activities as $activity)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $activity->description }}</span>
                                @if ($activity->log_name)
                                    <span class="badge text-bg-light ms-1">{{ $activity->log_name }}</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $activity->causer?->name ?? 'System' }}</td>
                            <td class="text-muted text-nowrap">{{ $activity->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </x-admin.datatable>
            </x-admin.card>
        </div>

        <div class="col-xl-5">
            <x-admin.card title="Recent logins" icon="bi-box-arrow-in-right" :flush="true">
                <x-slot:actions>
                    @can('activity.view')
                        <a href="{{ route('admin.activity.logins') }}" class="btn btn-sm btn-outline-secondary">View all</a>
                    @endcan
                </x-slot:actions>

                <x-admin.datatable :headers="['User', 'Result', 'When']" :rows="$logins"
                                   empty="No logins recorded yet." empty-icon="bi-person-x">
                    @foreach ($logins as $login)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $login->authenticatable?->name ?? $login->email }}</div>
                                <div class="text-muted" style="font-size:.74rem">{{ $login->guard }} · {{ $login->ip_address }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $login->successful ? 'text-bg-success' : 'text-bg-danger' }}">
                                    {{ $login->successful ? 'Success' : 'Failed' }}
                                </span>
                            </td>
                            <td class="text-muted text-nowrap">{{ $login->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </x-admin.datatable>
            </x-admin.card>
        </div>
    </div>
    @endif

    <div class="row g-3 mt-0">
        <div class="col-12">
            <x-admin.card title="Development roadmap" subtitle="9 phases — Customer Website + Admin Panel" icon="bi-map">
                <div class="row g-2">
                    @foreach ($phases as $phase)
                        <div class="col-6 col-md-4 col-xl-3">
                            @php
                                $tone = match ($phase['status']) {
                                    'done' => ['wrap' => 'border-success bg-success-subtle', 'badge' => 'text-bg-success'],
                                    'current' => ['wrap' => 'border-warning bg-warning-subtle', 'badge' => 'text-bg-warning'],
                                    default => ['wrap' => '', 'badge' => 'text-bg-light'],
                                };
                            @endphp

                            <div class="d-flex align-items-center gap-2 p-2 rounded border {{ $tone['wrap'] }}">
                                <span class="badge rounded-pill {{ $tone['badge'] }}">
                                    @if ($phase['status'] === 'done')
                                        <i class="bi bi-check-lg"></i>
                                    @else
                                        {{ $phase['no'] }}
                                    @endif
                                </span>
                                <div class="lh-sm">
                                    <div class="fw-semibold" style="font-size:.8rem">{{ $phase['name'] }}</div>
                                    <div class="text-muted" style="font-size:.72rem">
                                        {{ $phase['weeks'] }} week{{ $phase['weeks'] == 1 ? '' : 's' }}
                                        @if ($phase['status'] === 'current') · in progress @endif
                                        @if ($phase['status'] === 'done') · complete @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>
    </div>

@endsection
