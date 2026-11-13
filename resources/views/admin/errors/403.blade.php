@extends('admin.layouts.app')

@section('title', 'Access denied')

@section('content')
    <div class="ad-card">
        <div class="ad-card__body text-center py-5">
            <i class="bi bi-shield-exclamation" style="font-size:3rem;color:var(--fc-gold)"></i>
            <h2 class="h4 mt-3 mb-2" style="color:var(--fc-navy)">Access denied</h2>
            <p class="text-muted mb-4">{{ $message ?? 'You do not have permission to open this page.' }}</p>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i> Back to dashboard
            </a>
        </div>
    </div>
@endsection
