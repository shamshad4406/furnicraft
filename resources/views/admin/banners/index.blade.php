@extends('admin.layouts.app')

@section('title', 'Banners')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Banners</li>
@endsection

@section('content')

<x-admin.card title="Banners" icon="bi-images"
              subtitle="{{ $banners->total() }} in total · {{ $liveCount }} on screen now" :flush="true">
    <x-slot:actions>
        @can('banners.create')
            <a href="{{ route('admin.banners.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Banner
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-5">
            <select name="placement" class="form-select form-select-sm">
                <option value="">Every placement</option>
                @foreach ($placements as $value => $label)
                    <option value="{{ $value }}" @selected(request('placement') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <select name="flag" class="form-select form-select-sm">
                <option value="">Any state</option>
                <option value="live" @selected(request('flag') === 'live')>On screen right now</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.banners.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>

    <x-admin.datatable :headers="['Banner', 'Placement', 'Runs', 'Clicks', 'State', ['label' => 'Actions', 'end' => true]]"
                       :rows="$banners" empty="No banners yet. The homepage falls back to its built-in slide."
                       empty-icon="bi-images">
        @foreach ($banners as $banner)
            <tr>
                <td>
                    <div class="d-flex gap-2 align-items-center">
                        @if ($banner->image_url)
                            <img src="{{ $banner->image_url }}" alt="" class="ad-banner-thumb">
                        @endif
                        <div>
                            <div class="fw-semibold">
                                {{ $banner->title }}
                                @if ($banner->highlight)
                                    <span class="text-warning">{{ $banner->highlight }}</span>
                                @endif
                            </div>
                            @if ($banner->subtitle)
                                <div class="text-muted" style="font-size:.74rem">
                                    {{ Str::limit($banner->subtitle, 70) }}
                                </div>
                            @endif
                            @if ($banner->cta_url)
                                <div style="font-size:.74rem">
                                    <i class="bi bi-link-45deg"></i>
                                    <code>{{ $banner->cta_url }}</code>
                                    @if ($banner->cta_label)
                                        <span class="text-muted">— “{{ $banner->cta_label }}”</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge text-bg-light border">{{ $banner->placement_label }}</span>
                    <div class="text-muted" style="font-size:.74rem">position {{ $banner->sort_order }}</div>
                </td>
                <td class="lh-sm text-muted" style="font-size:.78rem">
                    <div>{{ $banner->starts_at?->format('d M Y H:i') ?? 'Any time' }}</div>
                    <div>{{ $banner->ends_at ? 'to '.$banner->ends_at->format('d M Y H:i') : 'no end date' }}</div>
                </td>
                <td><span class="badge text-bg-light border">{{ number_format($banner->click_count) }}</span></td>
                <td><span class="badge text-bg-{{ $banner->state_badge }}">{{ $banner->state_label }}</span></td>
                <td class="text-end text-nowrap">
                    @can('banners.edit')
                        <form method="POST" action="{{ route('admin.banners.reorder', $banner) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <input type="hidden" name="direction" value="up">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Move up">
                                <i class="bi bi-arrow-up"></i>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.banners.reorder', $banner) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <input type="hidden" name="direction" value="down">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Move down">
                                <i class="bi bi-arrow-down"></i>
                            </button>
                        </form>

                        <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-secondary"
                           data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="{{ route('admin.banners.toggle', $banner) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-{{ $banner->is_active ? 'warning' : 'success' }}"
                                    data-bs-toggle="tooltip" title="{{ $banner->is_active ? 'Switch off' : 'Switch on' }}">
                                <i class="bi {{ $banner->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            </button>
                        </form>
                    @endcan

                    @can('banners.delete')
                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="d-inline"
                              data-confirm="Delete the banner “{{ $banner->title }}”?">
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
