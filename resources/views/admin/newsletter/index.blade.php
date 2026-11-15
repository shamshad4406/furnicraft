@extends('admin.layouts.app')

@section('title', 'Newsletter')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Newsletter</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'subscribed',   'label' => 'Subscribed',          'icon' => 'bi-envelope-check', 'tone' => 'teal', 'status' => 'subscribed'],
        ['key' => 'pending',      'label' => 'Yet to confirm',      'icon' => 'bi-hourglass',      'tone' => 'gold', 'status' => 'pending'],
        ['key' => 'unsubscribed', 'label' => 'Unsubscribed',        'icon' => 'bi-envelope-slash', 'tone' => 'rose', 'status' => 'unsubscribed'],
        ['key' => 'thisMonth',    'label' => 'Joined this month',   'icon' => 'bi-calendar-plus',  'tone' => 'navy', 'status' => null],
    ] as $tile)
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.newsletter.index', $tile['status'] ? ['status' => $tile['status']] : []) }}"
               class="ad-card ad-card--link d-block text-decoration-none">
                <div class="ad-card__body">
                    <div class="ad-stat ad-stat--{{ $tile['tone'] }}">
                        <div class="ad-stat__icon"><i class="bi {{ $tile['icon'] }}"></i></div>
                        <div>
                            <div class="ad-stat__value">{{ number_format($summary[$tile['key']]) }}</div>
                            <div class="ad-stat__label">{{ $tile['label'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<x-admin.card title="Mailing list" icon="bi-envelope-paper"
              subtitle="{{ $subscribers->total() }} in this view" :flush="true">
    <x-slot:actions>
        <a href="{{ route('admin.newsletter.export', request()->only('q', 'status')) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-7">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search by email or name">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Everyone on the list</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.newsletter.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>

    <div class="px-3 pt-3">
        <p class="small text-muted mb-0">
            FurniCraft keeps the list; it does not send the campaigns. Export the CSV into whatever
            sends your mail — the unsubscribe link travels with each row, so every message can offer
            a way out.
        </p>
    </div>

    <x-admin.datatable :headers="['Email', 'Name', 'Came from', 'Joined', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$subscribers" empty="Nobody has subscribed yet." empty-icon="bi-envelope-paper">
        @foreach ($subscribers as $subscriber)
            <tr>
                <td>
                    <span class="fw-semibold">{{ $subscriber->email }}</span>
                    @if ($subscriber->customer_id)
                        <div style="font-size:.74rem">
                            <a href="{{ route('admin.customers.show', $subscriber->customer_id) }}">
                                <i class="bi bi-person-heart"></i> account holder
                            </a>
                        </div>
                    @endif
                </td>
                <td>{{ $subscriber->name ?: '—' }}</td>
                <td><span class="badge text-bg-light border">{{ $subscriber->source ?: 'website' }}</span></td>
                <td class="lh-sm text-muted" style="font-size:.78rem">
                    <div>{{ $subscriber->created_at->format('d M Y') }}</div>
                    @if ($subscriber->confirmed_at)
                        <div>confirmed {{ $subscriber->confirmed_at->format('d M Y') }}</div>
                    @endif
                </td>
                <td>
                    <span class="badge text-bg-{{ $subscriber->status_badge }}">{{ $subscriber->status_label }}</span>
                    @if ($subscriber->unsubscribe_reason)
                        <div class="text-muted" style="font-size:.74rem">
                            {{ Str::limit($subscriber->unsubscribe_reason, 40) }}
                        </div>
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    @can('newsletter.edit')
                        @if ($subscriber->status !== 'unsubscribed')
                            <form method="POST" action="{{ route('admin.newsletter.unsubscribe', $subscriber) }}"
                                  class="d-inline"
                                  data-confirm="Take {{ $subscriber->email }} off the list?">
                                @csrf @method('PATCH')
                                <input type="hidden" name="reason" value="Removed by staff">
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip"
                                        title="Take off the list">
                                    <i class="bi bi-envelope-slash"></i>
                                </button>
                            </form>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
