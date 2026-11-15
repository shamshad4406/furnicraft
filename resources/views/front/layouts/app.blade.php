<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', settings('company_name', 'FurniCraft')) — {{ settings('company_headline', 'Premium Quality Furniture') }}</title>
    <meta name="description" content="@yield('meta_description', settings('company_about', 'Quality furniture for modern living.'))">
    {{-- Most pages should be indexed; a page addressed by a one-off token
         (an order tracking link, say) overrides this with noindex. A shop
         that has not gone live yet turns indexing off shop-wide. --}}
    <meta name="robots" content="@yield('robots', settings()->bool('seo_allow_indexing', true) ? 'index, follow' : 'noindex, nofollow')">

    {{-- The canonical URL leaves query strings off, so a filtered listing
         and a sorted one do not compete with each other in search results. --}}
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph, so a link pasted into WhatsApp shows something useful
         rather than a bare address. --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ settings('company_name', 'FurniCraft') }}">
    <meta property="og:title" content="@yield('title', settings('company_name', 'FurniCraft'))">
    <meta property="og:description" content="@yield('meta_description', settings('company_about', 'Quality furniture for modern living.'))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', setting_file('company_logo') ?? asset('images/logo.svg'))">
    <meta name="twitter:card" content="summary_large_image">

    @if (filled(settings('google_site_verification')))
        <meta name="google-site-verification" content="{{ settings('google_site_verification') }}">
    @endif

    <link rel="icon" href="{{ setting_file('company_favicon') ?? asset('images/logo.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="preload" href="{{ asset('assets/css/fonts/bootstrap-icons.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    @stack('styles')

    {{-- Structured data, so search engines read the shop rather than guess
         at it. Pushed by whichever page has something worth describing. --}}
    @stack('schema')

    @if (filled(settings('google_analytics_id')))
        {{-- The measurement ID rides on the tag, because the CSP allows no
             inline script for the usual gtag snippet. --}}
        <script src="{{ asset_v('assets/js/analytics.js') }}"
                data-ga-id="{{ settings('google_analytics_id') }}" defer></script>
    @endif
</head>
<body>

@include('front.partials.topbar')
@include('front.partials.header')

@if (session('success') || session('error') || $errors->any())
    <div class="container-fc pt-3">
        @include('front.partials.alerts')
    </div>
@endif

<main>
    @yield('content')
</main>

@include('front.partials.footer')

<script src="{{ asset_v('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset_v('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
