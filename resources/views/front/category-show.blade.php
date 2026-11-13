@extends('front.layouts.app')

@php
    // @section(name, null) makes Blade open an output buffer it never closes,
    // and Str::limit(null) returns null — so this must never be null.
    $metaDescription = (string) ($category->meta_description
        ?: Str::limit((string) $category->description, 150)
        ?: settings('company_about', ''));
@endphp

@section('title', (string) ($category->meta_title ?: $category->name))
@section('meta_description', $metaDescription)

@section('content')

    <section class="fc-page-hero">
        <div class="container-fc">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                @if ($category->image_url)
                    <img src="{{ $category->image_url }}" alt=""
                         style="width:64px;height:52px;object-fit:contain;background:#fff;border-radius:.45rem;padding:.3rem">
                @endif
                <div>
                    <h1>{{ $category->name }}</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('categories') }}">Categories</a></li>
                            @if ($category->parent)
                                <li class="breadcrumb-item">
                                    <a href="{{ route('category.show', $category->parent->slug) }}">{{ $category->parent->name }}</a>
                                </li>
                            @endif
                            <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
                        </ol>
                    </nav>
                </div>
            </div>

            @if ($category->description)
                <p class="mb-0 mt-2 text-muted" style="max-width:780px">{{ $category->description }}</p>
            @endif
        </div>
    </section>

    <section class="fc-section">
        <div class="container-fc">

            @if ($category->children->isNotEmpty())
                <div class="fc-chips">
                    <span class="fc-chip is-active">All {{ $category->name }}</span>
                    @foreach ($category->children as $child)
                        <a href="{{ route('category.show', $child->slug) }}" class="fc-chip">
                            {{ $child->name }}
                            <span class="fc-chip__count">{{ $child->active_products_count }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-3">
                    @include('front.partials.filters', [
                        'action' => route('category.show', $category->slug),
                        'lockCategory' => true,
                    ])
                </div>

                <div class="col-lg-9">
                    @include('front.partials.listing-toolbar')

                    @if ($products->isEmpty())
                        @include('front.partials.listing-empty', [
                            'clearUrl' => route('category.show', $category->slug),
                        ])
                    @else
                        <div class="row g-3 row-cols-2 row-cols-md-3">
                            @foreach ($products as $product)
                                <div class="col">
                                    @include('front.partials.product-card', ['product' => $product, 'showCategory' => true])
                                </div>
                            @endforeach
                        </div>

                        @if ($products->hasPages())
                            <div class="fc-pagination">{{ $products->links('pagination::bootstrap-5') }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </section>

@endsection
