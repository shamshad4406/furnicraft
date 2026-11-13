<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', settings('company_name', 'FurniCraft')) — {{ settings('company_headline', 'Premium Quality Furniture') }}</title>
    <meta name="description" content="@yield('meta_description', settings('company_about', 'Quality furniture for modern living.'))">

    <link rel="icon" href="{{ setting_file('company_favicon') ?? asset('images/logo.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    @stack('styles')
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
