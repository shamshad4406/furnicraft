@extends('admin.layouts.app')

@php $editing = $banner->exists; @endphp

@section('title', $editing ? 'Edit Banner' : 'Add Banner')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.banners.index') }}">Banners</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? Str::limit($banner->title, 30) : 'Add' }}</li>
@endsection

@section('content')

<form method="POST" enctype="multipart/form-data" novalidate
      action="{{ $editing ? route('admin.banners.update', $banner) : route('admin.banners.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="What it says" icon="bi-card-heading">
                <div class="row g-3">
                    <x-admin.select name="placement" label="Placement" col="col-md-6" required
                                    :options="$placements" :value="$banner->placement"
                                    help="The homepage slider shows hero banners in order." />

                    <x-admin.input name="sort_order" label="Position" type="number" min="0" max="999" col="col-md-6"
                                   :value="$banner->sort_order ?? 0"
                                   help="Lower numbers come first. You can also nudge the order from the list." />

                    <x-admin.input name="title" label="Headline" :value="$banner->title" required col="col-md-7"
                                   help="The main line, in the normal colour." />

                    <x-admin.input name="highlight" label="Highlighted words" :value="$banner->highlight" col="col-md-5"
                                   help="Shown in gold after the headline. Optional." />

                    <x-admin.textarea name="subtitle" label="Supporting line" :value="$banner->subtitle" rows="2"
                                      help="One short sentence. Optional." />

                    <x-admin.input name="cta_label" label="Button text" :value="$banner->cta_label" col="col-md-5"
                                   help="Leave blank for a banner with no button." />

                    <x-admin.input name="cta_url" label="Button link" :value="$banner->cta_url" col="col-md-7"
                                   placeholder="/products?category=sofas"
                                   help="A path on this site, starting with a slash." />
                </div>
            </x-admin.card>

            <x-admin.card title="When it runs" icon="bi-calendar-range" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="starts_at" label="Starts" type="datetime-local" col="col-md-6"
                                   :value="old('starts_at', $banner->starts_at?->format('Y-m-d\TH:i'))"
                                   help="Blank = as soon as it is switched on." />

                    <x-admin.input name="ends_at" label="Ends" type="datetime-local" col="col-md-6"
                                   :value="old('ends_at', $banner->ends_at?->format('Y-m-d\TH:i'))"
                                   help="Blank = runs until switched off. It stops on its own." />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Status" icon="bi-toggles">
                <x-admin.switcher name="is_active" col="col-12" :checked="$banner->is_active ?? true">
                    Banner is switched on
                </x-admin.switcher>

                @if ($editing)
                    <dl class="row mb-0 small mt-3">
                        <dt class="col-7 text-muted fw-normal">State</dt>
                        <dd class="col-5 text-end">
                            <span class="badge text-bg-{{ $banner->state_badge }}">{{ $banner->state_label }}</span>
                        </dd>
                        <dt class="col-7 text-muted fw-normal">Clicks</dt>
                        <dd class="col-5 fw-semibold text-end">{{ number_format($banner->click_count) }}</dd>
                        <dt class="col-7 text-muted fw-normal">Added</dt>
                        <dd class="col-5 fw-semibold text-end">{{ $banner->created_at->format('d M Y') }}</dd>
                    </dl>
                @endif
            </x-admin.card>

            <x-admin.card title="Image" icon="bi-image" class="mt-3">
                @if ($banner->image_url)
                    <div class="ad-banner-preview mb-2">
                        <img src="{{ $banner->image_url }}" alt="" id="bannerPreview">
                    </div>
                @else
                    <div class="ad-banner-preview mb-2 d-grid" style="height:110px;place-items:center">
                        <img src="" alt="" id="bannerPreview" style="display:none">
                        <span class="text-muted small">No image yet</span>
                    </div>
                @endif

                <input type="file" name="image" accept="image/*" data-preview="#bannerPreview"
                       class="form-control form-control-sm @error('image') is-invalid @enderror">
                @error('image') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <div class="form-text">Landscape, about 1920×720. JPG, PNG or WebP up to 8 MB.</div>

                <hr class="my-3">

                @if ($banner->mobile_image_path)
                    <div class="ad-banner-preview mb-2">
                        <img src="{{ $banner->mobile_image_url }}" alt="" id="bannerMobilePreview">
                    </div>
                @endif

                <label class="form-label">Phone crop</label>
                <input type="file" name="mobile_image" accept="image/*" data-preview="#bannerMobilePreview"
                       class="form-control form-control-sm @error('mobile_image') is-invalid @enderror">
                @error('mobile_image') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <div class="form-text">Optional. The main image is used on phones when this is empty.</div>

                <div class="row g-3 mt-2">
                    <x-admin.input name="image_alt" label="Describe the image" :value="$banner->image_alt"
                                   help="Read aloud by screen readers, and shown if the image fails to load." />
                </div>
            </x-admin.card>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Changes' : 'Create Banner' }}
                </button>
                <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
