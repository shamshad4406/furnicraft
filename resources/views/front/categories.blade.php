@extends('front.layouts.app')

@section('title', 'Categories')
@section('meta_description', 'Browse all FurniCraft furniture categories — steel furniture, sofas, beds, chairs, tables and office furniture.')

@section('content')

    <section class="fc-page-hero">
        <div class="container-fc">
            <h1>Categories</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Categories</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="fc-section">
        <div class="container-fc">

            @forelse ($categories as $category)
                <div class="fc-cat-group">
                    <div class="fc-cat-group__head">
                        <img src="{{ $category->image_url ?? asset('images/logo.svg') }}" alt="" loading="lazy">
                        <div>
                            <h2><a href="{{ route('category.show', $category->slug) }}">{{ $category->name }}</a></h2>
                            <p>
                                <span>{{ $category->total_active_products }} {{ Str::plural('product', $category->total_active_products) }}</span>@if ($category->description) · {{ $category->description }} @endif
                            </p>
                        </div>
                    </div>

                    @if ($category->children->isNotEmpty())
                        <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4">
                            @foreach ($category->children as $child)
                                <div class="col">
                                    <a href="{{ route('category.show', $child->slug) }}" class="fc-subcat-card">
                                        <span class="fc-subcat-card__name">{{ $child->name }}</span>
                                        <span class="fc-subcat-card__count">{{ $child->active_products_count }} {{ Str::plural('product', $child->active_products_count) }}</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="fc-phase-note text-center mx-auto" style="max-width:640px">
                    <i class="bi bi-inboxes fs-2 d-block mb-2" style="color:var(--fc-gold)"></i>
                    <h2 class="h5 mb-2" style="color:var(--fc-navy)">No categories yet</h2>
                    <p class="mb-0 text-muted">Categories added in the admin panel appear here.</p>
                </div>
            @endforelse

            @if ($categories->isNotEmpty())
                <div class="text-center mt-4">
                    <a href="{{ route('products') }}" class="btn btn-navy d-inline-flex align-items-center gap-2">
                        View All Products <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @endif
        </div>
    </section>

@endsection
