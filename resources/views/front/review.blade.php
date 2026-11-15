@extends('front.layouts.app')

@section('title', 'Review '.$product->name)
@section('robots', 'noindex, nofollow')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Write a review</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('product.show', $product->slug) }}">{{ Str::limit($product->name, 30) }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Review</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-7">
                <div class="fc-panel">
                    <div class="fc-enquiry-product">
                        @if ($product->thumbnail_url)
                            <img src="{{ $product->thumbnail_url }}" alt="" width="64" height="64" loading="lazy">
                        @endif
                        <div>
                            <strong>{{ $product->name }}</strong>
                            <div class="small text-muted">SKU {{ $product->sku }}</div>
                        </div>
                    </div>

                    @unless ($eligibility['allowed'])
                        <div class="alert alert-warning small">{{ $eligibility['reason'] }}</div>
                    @endunless

                    <form method="POST" action="{{ route('review.store', $product->slug) }}" novalidate>
                        @csrf

                        {{-- Hidden from people, irresistible to bots. --}}
                        <div class="fc-hp" aria-hidden="true">
                            <label for="website">Leave this empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <fieldset class="mb-3">
                            <legend class="form-label">How many stars? <span class="text-danger">*</span></legend>

                            {{-- Radio buttons styled as stars: the control is
                                 a real radio group, so it works with a
                                 keyboard and a screen reader. --}}
                            <div class="fc-star-input">
                                @for ($star = 5; $star >= 1; $star--)
                                    <input type="radio" name="rating" value="{{ $star }}"
                                           id="rating{{ $star }}" @checked((int) old('rating') === $star) required>
                                    <label for="rating{{ $star }}" title="{{ $star }} star{{ $star === 1 ? '' : 's' }}">
                                        <i class="bi bi-star-fill"></i>
                                        <span class="visually-hidden">{{ $star }} star{{ $star === 1 ? '' : 's' }}</span>
                                    </label>
                                @endfor
                            </div>
                            @error('rating') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </fieldset>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Your name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" required maxlength="120"
                                       value="{{ old('name', $customer?->name) }}"
                                       class="form-control @error('name') is-invalid @enderror">
                                <div class="form-text">Shown as a first name and an initial.</div>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" required maxlength="255"
                                       value="{{ old('email', $customer?->email) }}"
                                       class="form-control @error('email') is-invalid @enderror">
                                <div class="form-text">Never shown. Used to check whether you bought this.</div>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" id="city" name="city" maxlength="120" value="{{ old('city') }}"
                                       class="form-control @error('city') is-invalid @enderror">
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="title" class="form-label">Sum it up</label>
                                <input type="text" id="title" name="title" maxlength="160" value="{{ old('title') }}"
                                       class="form-control @error('title') is-invalid @enderror"
                                       placeholder="Solid, comfortable, arrived on time">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="body" class="form-label">Your review <span class="text-danger">*</span></label>
                                <textarea id="body" name="body" rows="5" required maxlength="3000"
                                          class="form-control @error('body') is-invalid @enderror"
                                          placeholder="How it looks, how it was delivered, how it has held up">{{ old('body') }}</textarea>
                                @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-navy" @disabled(! $eligibility['allowed'])>
                                Send my review
                            </button>
                            <a href="{{ route('product.show', $product->slug) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>

                        <p class="small text-muted mt-3 mb-0">
                            Reviews are read by a person before they appear, usually within a working
                            day. We publish the ones we disagree with too.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
