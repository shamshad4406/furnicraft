@extends('admin.layouts.app')

@section('title', 'Reviews')
@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Reviews</li>
@endsection

@section('content')

<div class="row g-2 mb-3">
    @foreach ([
        ['key' => 'pending',  'label' => 'Waiting to be read', 'icon' => 'bi-hourglass',     'tone' => 'gold', 'query' => ['status' => 'pending'],  'raw' => false],
        ['key' => 'approved', 'label' => 'Published',          'icon' => 'bi-check2-circle', 'tone' => 'teal', 'query' => ['status' => 'approved'], 'raw' => false],
        ['key' => 'featured', 'label' => 'On the homepage',    'icon' => 'bi-house-heart',   'tone' => 'navy', 'query' => ['status' => 'approved', 'flag' => 'featured'], 'raw' => false],
        ['key' => 'average',  'label' => 'Average rating',     'icon' => 'bi-star-fill',     'tone' => 'gold', 'query' => ['status' => 'approved'], 'raw' => true],
    ] as $tile)
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.reviews.index', $tile['query']) }}"
               class="ad-card ad-card--link d-block text-decoration-none">
                <div class="ad-card__body">
                    <div class="ad-stat ad-stat--{{ $tile['tone'] }}">
                        <div class="ad-stat__icon"><i class="bi {{ $tile['icon'] }}"></i></div>
                        <div>
                            <div class="ad-stat__value">
                                {{ $tile['raw'] ? number_format($summary[$tile['key']], 2) : $summary[$tile['key']] }}
                            </div>
                            <div class="ad-stat__label">{{ $tile['label'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<x-admin.card title="Reviews" subtitle="{{ $reviews->total() }} in this view" icon="bi-star" :flush="true">
    <form method="GET" class="row g-2 p-3 border-bottom bg-light">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Name, email, product, or anything they wrote">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="all" @selected($status === 'all')>Every review</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="rating" class="form-select form-select-sm">
                <option value="">Any rating</option>
                @foreach ([5, 4, 3, 2, 1] as $stars)
                    <option value="{{ $stars }}" @selected((int) request('rating') === $stars)>
                        {{ $stars }} star{{ $stars === 1 ? '' : 's' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i></button>
            <a href="{{ route('admin.reviews.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    @forelse ($reviews as $review)
        <div class="ad-card__body border-bottom">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="ad-stars" role="img" aria-label="{{ $review->stars }} out of 5">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="bi bi-star{{ $i <= $review->stars ? '-fill' : '' }}"></i>
                            @endfor
                        </span>

                        <span class="badge text-bg-{{ $review->status_badge }}">{{ $review->status_label }}</span>

                        @if ($review->is_verified)
                            <span class="badge text-bg-light border text-success">
                                <i class="bi bi-patch-check-fill"></i> Verified purchase
                            </span>
                        @endif

                        @if ($review->is_featured)
                            <span class="badge text-bg-light border"><i class="bi bi-house-heart"></i> Homepage</span>
                        @endif
                    </div>

                    @if ($review->title)
                        <strong class="d-block">{{ $review->title }}</strong>
                    @endif

                    <p class="small mb-1" style="white-space: pre-line">{{ $review->body }}</p>

                    <div class="small text-muted">
                        {{ $review->name }} · {{ $review->email }}
                        @if ($review->city) · {{ $review->city }} @endif
                        · {{ $review->created_at->format('d M Y') }}
                    </div>

                    <div class="small text-muted">
                        @if ($review->product)
                            <a href="{{ route('admin.products.edit', $review->product) }}">{{ $review->product->name }}</a>
                        @else
                            <span>Product removed</span>
                        @endif
                    </div>

                    @if ($review->reply)
                        <div class="ad-reply mt-2">
                            <strong>You replied</strong>
                            <p class="mb-0 small">{{ $review->reply }}</p>
                        </div>
                    @endif

                    @if ($review->moderation_note)
                        <div class="small text-muted mt-1"><em>Note: {{ $review->moderation_note }}</em></div>
                    @endif
                </div>

                {{-- ------------------------------------------- actions --}}
                <div class="col-lg-4">
                    @can('reviews.edit')
                        @if ($review->status !== 'approved')
                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-primary w-100">Publish it</button>
                            </form>
                        @endif

                        @if ($review->status !== 'rejected' && $review->status !== 'spam')
                            <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <input type="text" name="moderation_note" maxlength="300"
                                       class="form-control form-control-sm mb-1" placeholder="Why (internal)">
                                <div class="d-flex gap-1">
                                    <button name="status" value="rejected" class="btn btn-sm btn-outline-secondary flex-grow-1">
                                        Do not publish
                                    </button>
                                    <button name="status" value="spam" class="btn btn-sm btn-outline-danger">Spam</button>
                                </div>
                            </form>
                        @endif

                        @if ($review->isPublished())
                            <form method="POST" action="{{ route('admin.reviews.feature', $review) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="featured" value="{{ $review->is_featured ? 0 : 1 }}">
                                <button class="btn btn-sm btn-outline-secondary w-100">
                                    {{ $review->is_featured ? 'Take off the homepage' : 'Show on the homepage' }}
                                </button>
                            </form>

                            <details>
                                <summary class="small" style="cursor:pointer">
                                    {{ $review->reply ? 'Edit your reply' : 'Reply in public' }}
                                </summary>
                                <form method="POST" action="{{ route('admin.reviews.reply', $review) }}" class="mt-2">
                                    @csrf @method('PATCH')
                                    <textarea name="reply" rows="3" maxlength="1000" required
                                              class="form-control form-control-sm mb-1">{{ $review->reply }}</textarea>
                                    <button class="btn btn-sm btn-outline-primary w-100">Save reply</button>
                                </form>
                            </details>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    @empty
        <div class="ad-empty">
            <i class="bi bi-star"></i>
            Nothing here.
            @if ($status === 'pending')
                <div class="small">Everything has been read.</div>
            @endif
        </div>
    @endforelse

    @if ($reviews->hasPages())
        <div class="ad-card__footer">{{ $reviews->links() }}</div>
    @endif
</x-admin.card>

@endsection
