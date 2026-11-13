@extends('admin.layouts.app')

@section('title', 'Activity Log')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Activity Log</li>
@endsection

@section('content')

<x-admin.card title="Audit trail" subtitle="{{ $activities->total() }} record(s)" icon="bi-clock-history" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.activity.logins') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-box-arrow-in-right me-1"></i> Login history
        </a>
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search description">
        </div>
        <div class="col-md-3">
            <select name="log" class="form-select form-select-sm">
                <option value="">All log types</option>
                @foreach ($logNames as $name)
                    <option value="{{ $name }}" @selected(request('log') === $name)>{{ ucfirst($name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="From date">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="To date">
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.activity.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['When', 'Description', 'Type', 'Subject', 'By']"
                       :rows="$activities" empty="No activity recorded for this filter.">
        @foreach ($activities as $activity)
            <tr>
                <td class="text-nowrap text-muted" style="font-size:.78rem">
                    {{ $activity->created_at->format('d M Y, H:i') }}
                </td>
                <td>
                    <span class="fw-semibold">{{ $activity->description }}</span>
                    @if ($activity->properties->isNotEmpty())
                        <button class="btn btn-link btn-sm p-0 ms-1 align-baseline" type="button"
                                data-bs-toggle="collapse" data-bs-target="#act{{ $activity->id }}"
                                aria-expanded="false">details</button>
                        <div class="collapse mt-1" id="act{{ $activity->id }}">
                            <pre class="bg-light border rounded p-2 mb-0"
                                 style="font-size:.72rem;max-height:200px;overflow:auto">{{ $activity->properties->toJson(JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </td>
                <td><span class="badge text-bg-light border">{{ $activity->log_name ?: 'default' }}</span></td>
                <td class="text-muted" style="font-size:.8rem">
                    {{ $activity->subject ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}
                </td>
                <td class="text-muted" style="font-size:.8rem">{{ $activity->causer?->name ?? 'System' }}</td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
