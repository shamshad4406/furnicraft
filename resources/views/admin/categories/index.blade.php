@extends('admin.layouts.app')

@section('title', 'Categories')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Categories</li>
@endsection

@section('content')

<x-admin.card title="Category tree" subtitle="Parent categories with their sub-categories" icon="bi-diagram-3" :flush="true">
    <x-slot:actions>
        @can('categories.create')
            <a href="{{ route('admin.categories.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Category
            </a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Search name or slug">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="hidden" @selected(request('status') === 'hidden')>Hidden</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <x-admin.datatable :headers="['Category', 'Slug', 'Products', 'Order', 'Status', ['label' => 'Actions', 'end' => true]]"
                       :rows="$categories" empty="No categories yet. Add your first one." empty-icon="bi-diagram-3">
        @foreach ($categories as $category)
            @include('admin.categories._row', ['category' => $category, 'depth' => 0])

            @foreach ($category->children as $child)
                @include('admin.categories._row', ['category' => $child, 'depth' => 1])
            @endforeach
        @endforeach
    </x-admin.datatable>
</x-admin.card>

@endsection
