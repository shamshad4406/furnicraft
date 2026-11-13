@extends('admin.layouts.app')

@section('title', 'Content Pages')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Pages</li>
@endsection

@section('content')

<x-admin.card title="Content pages" subtitle="{{ $pages->total() }} page(s) — About, FAQ, policies and anything else"
              icon="bi-file-earmark-richtext" :flush="true">
    <x-slot:actions>
        @can('cms.create')
            <a href="{{ route('admin.cms.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Page
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-9">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search title or slug">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
            <a href="{{ route('admin.cms.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <x-admin.datatable :headers="['Page', 'URL', 'In footer', 'Order', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$pages" empty="No pages yet." empty-icon="bi-file-earmark-richtext">
        @foreach ($pages as $page)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $page->title }}</div>
                    @if ($page->excerpt)
                        <div class="text-muted" style="font-size:.74rem">{{ Str::limit($page->excerpt, 70) }}</div>
                    @endif
                    @if ($page->is_system)
                        <span class="badge text-bg-light border mt-1" data-bs-toggle="tooltip"
                              title="Linked from the website — the slug is fixed and it cannot be deleted">System</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('page', $page->slug) }}" target="_blank" rel="noopener"
                       class="text-muted" style="font-size:.8rem">
                        <code>/{{ $page->slug }}</code> <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </td>
                <td>
                    @if ($page->show_in_footer)
                        <i class="bi bi-check-circle-fill text-success"></i>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-muted">{{ $page->sort_order }}</td>
                <td>
                    <span class="badge {{ $page->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $page->is_active ? 'Published' : 'Hidden' }}
                    </span>
                </td>
                <td class="text-end text-nowrap">
                    @can('cms.edit')
                        <a href="{{ route('admin.cms.edit', $page) }}" class="btn btn-sm btn-outline-secondary"
                           data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="{{ route('admin.cms.toggle', $page) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-{{ $page->is_active ? 'warning' : 'success' }}"
                                    data-bs-toggle="tooltip" title="{{ $page->is_active ? 'Hide' : 'Publish' }}">
                                <i class="bi {{ $page->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                            </button>
                        </form>
                    @endcan

                    @can('cms.delete')
                        @unless ($page->is_system)
                            <form method="POST" action="{{ route('admin.cms.destroy', $page) }}" class="d-inline"
                                  data-confirm="Delete the page {{ $page->title }}?">
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
