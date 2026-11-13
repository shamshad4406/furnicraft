<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Sign in') — {{ settings('company_name', 'FurniCraft') }} Admin</title>

    <link rel="icon" href="{{ setting_file('company_favicon') ?? asset('images/logo.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset_v('assets/css/admin.css') }}">
</head>
<body>

<div class="ad-login">
    <aside class="ad-login__aside">
        <div class="d-flex align-items-center gap-2 mb-4">
            <img src="{{ asset('images/logo.svg') }}" alt="" style="width:46px;filter:brightness(0) invert(1)">
            <span class="fs-4 fw-bold">Furni<span style="color:var(--fc-gold)">Craft</span></span>
        </div>

        <h1>Admin <span>Control Panel</span></h1>
        <p class="mt-2 mb-0" style="color:rgba(255,255,255,.75)">
            {{ settings('company_headline', 'Premium Quality Furniture for a Better Living') }}
        </p>

        <ul>
            <li><i class="bi bi-shield-check"></i> Role-based access for Admin, Manager, Sales, Inventory and Delivery</li>
            <li><i class="bi bi-clock-history"></i> Every change recorded in the activity log</li>
            <li><i class="bi bi-lock"></i> Login throttling and password reset by email</li>
        </ul>
    </aside>

    <section class="ad-login__form">
        <div class="ad-login__inner">
            @include('admin.partials.alerts')
            @yield('content')
        </div>
    </section>
</div>

<script src="{{ asset_v('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset_v('assets/js/admin.js') }}"></script>
</body>
</html>
