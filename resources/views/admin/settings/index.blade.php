@extends('admin.layouts.app')

@section('title', 'Website Settings')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Website Settings</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-lg-3">
        <x-admin.card>
            <nav class="nav flex-column ad-settings-nav">
                @foreach ($tabs as $key => $tab)
                    <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                       href="{{ route('admin.settings.index', ['tab' => $key]) }}">
                        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>
        </x-admin.card>
    </div>

    <div class="col-lg-9">
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="group" value="{{ $activeTab }}">

            <x-admin.card :title="$tabs[$activeTab]['label']" :icon="$tabs[$activeTab]['icon']">
                @include('admin.settings.tabs.'.$activeTab)

                <x-slot:footer>
                    @can('settings.edit')
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save {{ $tabs[$activeTab]['label'] }}
                        </button>
                    @else
                        <span class="text-muted small">You have read-only access to settings.</span>
                    @endcan
                </x-slot:footer>
            </x-admin.card>
        </form>
    </div>
</div>

@endsection
