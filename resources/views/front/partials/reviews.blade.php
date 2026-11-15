{{--
    The review block on a product page.

    $product, $reviews, $reviewBreakdown, $canReview
--}}
<section class="fc-section" id="reviews">
    <div class="container-fc">
        <div class="fc-section-title"><h2>Customer Reviews</h2></div>

        <div class="row g-4">
            {{-- ------------------------------------------------ summary --}}
            <div class="col-lg-4">
                <div class="fc-panel h-100">
                    @if ($product->hasRating())
                        <div class="fc-rating-summary">
                            <strong>{{ number_format((float) $product->rating_average, 1) }}</strong>
                            <div class="fc-stars" role="img"
                                 aria-label="{{ number_format((float) $product->rating_average, 1) }} out of 5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star{{ $i <= round($product->rating_average) ? '-fill' : '' }}"></i>
                                @endfor
                            </div>
                            <span class="text-muted small">
                                {{ $product->rating_count }} review{{ $product->rating_count === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            @foreach ($reviewBreakdown as $row)
                                <div class="fc-rating-bar">
                                    <span class="fc-rating-bar__label">{{ $row['star'] }} <i class="bi bi-star-fill"></i></span>
                                    <span class="fc-rating-bar__track">
                                        <span style="width: {{ $row['percent'] }}%"></span>
                                    </span>
                                    <span class="fc-rating-bar__count">{{ $row['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted small mb-0">
                            Nobody has reviewed this yet. If you have bought it, yours would be the
                            first — and the most useful.
                        </p>
                    @endif

                    @if ($canReview)
                        <a href="{{ route('review.create', $product->slug) }}" class="btn btn-navy w-100 mt-3">
                            <i class="bi bi-pencil"></i> Write a review
                        </a>
                    @endif
                </div>
            </div>

            {{-- ------------------------------------------------- reviews --}}
            <div class="col-lg-8">
                @forelse ($reviews as $review)
                    <article class="fc-review">
                        <header>
                            <div class="fc-stars" role="img" aria-label="{{ $review->stars }} out of 5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star{{ $i <= $review->stars ? '-fill' : '' }}"></i>
                                @endfor
                            </div>

                            @if ($review->title)
                                <h3>{{ $review->title }}</h3>
                            @endif
                        </header>

                        <p class="fc-review__body">{{ $review->body }}</p>

                        <footer>
                            <strong>{{ $review->display_name }}</strong>
                            @if ($review->city)<span>{{ $review->city }}</span>@endif
                            <span>{{ $review->created_at->format('d M Y') }}</span>

                            @if ($review->is_verified)
                                {{-- Derived from a delivered order, never
                                     ticked by hand — it is the one claim here
                                     that carries weight. --}}
                                <span class="fc-verified">
                                    <i class="bi bi-patch-check-fill"></i> Verified purchase
                                </span>
                            @endif
                        </footer>

                        @if ($review->reply)
                            <div class="fc-review__reply">
                                <strong>{{ settings('company_name', 'FurniCraft') }} replied</strong>
                                <p>{{ $review->reply }}</p>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="fc-empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>No reviews yet</h3>
                        <p>Reviews here come from people who have bought from us.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
