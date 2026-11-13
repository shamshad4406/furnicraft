@extends('admin.layouts.app')

@php $editing = $brand->exists; @endphp

@section('title', $editing ? 'Edit Brand' : 'Add Brand')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.brands.index') }}">Brands</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $brand->name : 'Add' }}</li>
@endsection

@section('content')

<form method="POST"
      action="{{ $editing ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
      enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Brand details" icon="bi-award">
                <div class="row g-3">
                    <x-admin.input name="name" label="Brand name" :value="$brand->name" required col="col-md-7" />
                    <x-admin.input name="sort_order" label="Sort order" type="number" min="0"
                                   :value="$brand->sort_order ?? 0" col="col-md-5" />
                    <x-admin.input name="slug" label="URL slug" :value="$brand->slug" col="col-12"
                                   help="Leave blank to generate it from the name." />
                    <x-admin.textarea name="description" label="Description" :value="$brand->description" rows="3" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Visibility" icon="bi-eye">
                <x-admin.switcher name="is_active" col="col-12" :checked="$brand->is_active ?? true">
                    Show this brand on the website
                </x-admin.switcher>
            </x-admin.card>

            <x-admin.card title="Logo" icon="bi-image" class="mt-3">
                <div class="text-center mb-3">
                    <img src="{{ $brand->logo_url ?? asset('images/logo.svg') }}" alt="" id="brandPreview"
                         style="max-height:90px;max-width:100%;object-fit:contain;background:#f4f6f9;border-radius:.4rem;padding:.5rem">
                </div>
                <input type="file" name="logo" accept="image/*" data-preview="#brandPreview"
                       class="form-control form-control-sm @error('logo') is-invalid @enderror">
                @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <div class="form-text">PNG, JPG or WebP up to 2 MB.</div>
            </x-admin.card>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Changes' : 'Create Brand' }}
                </button>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
