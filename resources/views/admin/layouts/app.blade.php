<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Dashboard') — {{ settings('company_name', 'FurniCraft') }} Admin</title>

    <link rel="icon" href="{{ setting_file('company_favicon') ?? asset('images/logo.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset_v('assets/css/admin.css') }}">
    @stack('styles')
</head>
<body>

<div class="ad-backdrop"></div>

@include('admin.partials.sidebar')

<div class="ad-main">
    @include('admin.partials.header')

    <div class="ad-content">
        @include('admin.partials.alerts')
        @yield('content')
    </div>

    <footer class="ad-footer">
        <span>{{ settings('company_copyright', '© '.date('Y').' FurniCraft') }}</span>
        <span>Phase 2 — Catalog Management · Laravel {{ app()->version() }}</span>
    </footer>
</div>

<script src="{{ asset_v('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset_v('assets/js/admin.js') }}"></script>
@stack('scripts')
</body>
</html>
