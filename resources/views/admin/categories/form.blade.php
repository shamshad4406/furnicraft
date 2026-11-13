@extends('admin.layouts.app')

@php $editing = $category->exists; @endphp

@section('title', $editing ? 'Edit Category' : 'Add Category')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Categories</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $category->name : 'Add' }}</li>
@endsection

@section('content')

<form method="POST"
      action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
      enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Category details" icon="bi-diagram-3">
                <div class="row g-3">
                    <x-admin.input name="name" label="Category name" :value="$category->name" required col="col-md-7" />
                    <x-admin.select name="parent_id" label="Parent category" col="col-md-5"
                                    :options="$parents" :value="$category->parent_id"
                                    placeholder="— None (top level) —"
                                    help="Leave empty for a top-level category." />

                    <x-admin.input name="slug" label="URL slug" :value="$category->slug" col="col-md-7"
                                   :help="$editing ? 'Changing this changes the category URL.' : 'Leave blank to generate it from the name.'" />
                    <x-admin.input name="sort_order" label="Sort order" type="number" min="0"
                                   :value="$category->sort_order ?? 0" col="col-md-5"
                                   help="Lower numbers appear first." />

                    <x-admin.textarea name="description" label="Description" :value="$category->description" rows="3" />
                </div>
            </x-admin.card>

            <x-admin.card title="SEO" subtitle="Used by the storefront in Phase 3 and the SEO tools in Phase 8"
                          icon="bi-search" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="meta_title" label="Meta title" :value="$category->meta_title" col="col-12"
                                   help="Up to 60 characters reads best in Google." />
                    <x-admin.textarea name="meta_description" label="Meta description" :value="$category->meta_description" rows="2" />
                    <x-admin.input name="meta_keywords" label="Meta keywords" :value="$category->meta_keywords" col="col-12" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Visibility" icon="bi-eye">
                <x-admin.switcher name="is_active" col="col-12" :checked="$category->is_active ?? true">
                    Show this category on the website
                </x-admin.switcher>
            </x-admin.card>

            <x-admin.card title="Category image" icon="bi-image" class="mt-3">
                <div class="text-center mb-3">
                    <img src="{{ $category->image_url ?? asset('images/categories/sofa.svg') }}" alt="" id="categoryPreview"
                         style="max-height:120px;max-width:100%;object-fit:contain;background:#f4f6f9;border-radius:.4rem;padding:.5rem">
                </div>
                <input type="file" name="image" accept="image/*" data-preview="#categoryPreview"
                       class="form-control form-control-sm @error('image') is-invalid @enderror">
                @error('image') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <div class="form-text">JPG, PNG or WebP up to 4 MB — converted to WebP automatically.</div>
            </x-admin.card>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Changes' : 'Create Category' }}
                </button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
