@extends('admin.layouts.app')

@section('title', 'Enquiries')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Enquiries</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'unanswered', 'label' => 'Not answered yet', 'icon' => 'bi-exclamation-circle', 'tone' => 'rose',  'query' => ['flag' => 'unanswered']],
        ['key' => 'open',       'label' => 'Open',             'icon' => 'bi-chat-left-text',      'tone' => 'gold',  'query' => ['flag' => 'open']],
        ['key' => 'mine',       'label' => 'Assigned to me',   'icon' => 'bi-person-check',        'tone' => 'navy',  'query' => ['flag' => 'mine']],
    ] as $tile)
        <div class="col-6 col-lg-4">
            <a href="{{ route('admin.enquiries.index', $tile['query']) }}"
               class="ad-card ad-card--link d-block text-decoration-none">
                <div class="ad-card__body">
                    <div class="ad-stat ad-stat--{{ $tile['tone'] }}">
                        <div class="ad-stat__icon"><i class="bi {{ $tile['icon'] }}"></i></div>
                        <div>
                            <div class="ad-stat__value">{{ $summary[$tile['key']] }}</div>
                            <div class="ad-stat__label">{{ $tile['label'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<x-admin.card title="Enquiries" subtitle="{{ $enquiries->total() }} enquiry(s)" icon="bi-chat-left-text" :flush="true">
    <x-slot:actions>
        @can('enquiries.create')
            <a href="{{ route('admin.enquiries.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Log an enquiry
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Reference, name, email, phone, or anything they wrote">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm">
                <option value="">Any kind</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="assigned_to" class="form-select form-select-sm">
                <option value="">Anyone</option>
                @foreach ($staff as $person)
                    <option value="{{ $person->id }}" @selected(request('assigned_to') == $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.enquiries.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <x-admin.datatable :headers="['Reference', 'From', 'About', 'Waiting', 'Assigned', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$enquiries" empty="Nothing matches this filter." empty-icon="bi-chat-left-text">
        @foreach ($enquiries as $enquiry)
            <tr>
                <td>
                    <a href="{{ route('admin.enquiries.show', $enquiry) }}" class="fw-semibold text-decoration-none">
                        {{ $enquiry->reference }}
                    </a>
                    <div class="small text-muted">{{ $enquiry->created_at->format('d M Y, H:i') }}</div>
                    @if ($enquiry->replies_count > 0)
                        <div class="small text-muted">
                            <i class="bi bi-chat-dots"></i> {{ $enquiry->replies_count }}
                        </div>
                    @endif
                </td>

                <td>
                    <div class="small fw-semibold">{{ $enquiry->name }}</div>
                    <div class="small text-muted">{{ $enquiry->phone }}</div>
                    <div class="small text-muted">{{ $enquiry->email }}</div>
                </td>

                <td class="small">
                    <span class="badge text-bg-light border">{{ $enquiry->type_label }}</span>
                    @if ($enquiry->product)
                        <div class="text-muted mt-1">{{ $enquiry->product->name }}</div>
                    @endif
                    <div class="text-muted">{{ Str::limit($enquiry->subject ?: $enquiry->message, 60) }}</div>
                </td>

                <td class="small">
                    @if ($enquiry->responded_at)
                        <span class="text-success"><i class="bi bi-check2"></i> Answered</span>
                        <div class="text-muted">{{ $enquiry->responded_at->diffForHumans() }}</div>
                    @elseif (! $enquiry->isOpen())
                        <span class="text-muted">—</span>
                    @else
                        @php $hours = $enquiry->waiting_hours; @endphp
                        <span class="{{ $hours >= 24 ? 'text-danger fw-semibold' : 'text-muted' }}">
                            {{ $hours >= 24 ? floor($hours / 24).' day(s)' : $hours.' hr' }}
                        </span>
                    @endif
                </td>

                <td class="small">
                    {{ $enquiry->assignee?->name ?? '—' }}
                </td>

                <td><span class="badge text-bg-{{ $enquiry->status_badge }}">{{ $enquiry->status_label }}</span></td>

                <td class="text-end">
                    <a href="{{ route('admin.enquiries.show', $enquiry) }}" class="btn btn-sm btn-outline-secondary">
                        {{ $enquiry->isOpen() ? 'Handle' : 'View' }}
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
