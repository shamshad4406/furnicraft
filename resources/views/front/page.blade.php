@extends('front.layouts.app')

@php
    $metaDescription = (string) ($page->meta_description ?: $page->excerpt ?: settings('company_about', ''));
@endphp

@section('title', (string) ($page->meta_title ?: $page->title))
@section('meta_description', $metaDescription)

@section('content')

    <section class="fc-page-hero">
        <div class="container-fc">
            <h1>{{ $page->title }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="fc-section">
        <div class="container-fc">
            <div class="row g-4">
                <div class="{{ $showContactDetails ? 'col-lg-7' : 'col-lg-9 mx-auto' }}">
                    <div class="fc-panel">
                        @if ($page->excerpt)
                            <p class="fc-page-lead">{{ $page->excerpt }}</p>
                        @endif

                        <div class="fc-prose">
                            {{-- Staff-authored Markdown; raw HTML is stripped. --}}
                            {!! Str::markdown((string) $page->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                </div>

                @if ($showContactDetails)
                    <div class="col-lg-5">
                        <div class="fc-panel fc-contact-card">
                            <h2 class="fc-panel__title">Reach us</h2>

                            @if (request('product'))
                                <div class="alert alert-warning py-2 px-3 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Enquiring about <strong>{{ request('product') }}</strong>. Quote this when you call or write —
                                    the enquiry form arrives in Phase 6.
                                </div>
                            @endif

                            <ul class="fc-contact-list">
                                <li>
                                    <i class="bi bi-telephone-fill"></i>
                                    <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '')) }}">{{ settings('company_phone') }}</a>
                                </li>
                                <li>
                                    <i class="bi bi-envelope-fill"></i>
                                    <a href="mailto:{{ settings('company_email') }}">{{ settings('company_email') }}</a>
                                </li>
                                @if (settings('company_whatsapp'))
                                    <li>
                                        <i class="bi bi-whatsapp"></i>
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', settings('company_whatsapp')) }}"
                                           target="_blank" rel="noopener noreferrer">WhatsApp us</a>
                                    </li>
                                @endif
                                <li>
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <span>{!! nl2br(e(settings('company_address', ''))) !!}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

@endsection
