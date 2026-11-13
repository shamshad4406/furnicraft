@php $me = auth('admin')->user(); @endphp

<header class="ad-header">
    <button class="btn btn-light d-lg-none" type="button" data-sidebar-toggle aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="flex-grow-1 min-w-0">
        <h1 class="ad-header__title">@yield('title', 'Dashboard')</h1>
        @hasSection('breadcrumbs')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    @yield('breadcrumbs')
                </ol>
            </nav>
        @endif
    </div>

    <a href="{{ route('home') }}" target="_blank" rel="noopener"
       class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex align-items-center gap-2">
        <i class="bi bi-globe2"></i> View Website
    </a>

    <div class="dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2 border" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="ad-avatar">
                @if ($me->avatar_url)
                    <img src="{{ $me->avatar_url }}" alt="">
                @else
                    {{ $me->initials }}
                @endif
            </span>
            <span class="d-none d-sm-block text-start lh-sm">
                <span class="d-block fw-semibold" style="font-size:.82rem">{{ $me->name }}</span>
                <span class="d-block text-muted" style="font-size:.72rem">{{ $me->roles->pluck('name')->join(', ') ?: 'No role' }}</span>
            </span>
            <i class="bi bi-chevron-down small"></i>
        </button>

        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><i class="bi bi-person me-2"></i>My Profile</a></li>
            @can('settings.view')
                <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
            @endcan
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                </form>
            </li>
        </ul>
    </div>
</header>
