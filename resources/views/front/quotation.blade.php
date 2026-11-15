@extends('front.layouts.app')

@section('title', 'Quotation '.$quotation->number)
@section('robots', 'noindex, nofollow')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Quotation {{ $quotation->number }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $quotation->number }}</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="fc-panel mb-3">
                    <h2 class="fc-panel__title">What we can do for you</h2>

                    @foreach ($quotation->items as $item)
                        <div class="fc-confirm__item">
                            <div>
                                <strong>{{ $item->name }}</strong>
                                @if ($item->description)
                                    <small class="d-block">{{ $item->description }}</small>
                                @endif
                                <small>Qty {{ $item->quantity }} × {{ money($item->unit_price) }}</small>
                            </div>
                            <strong>{{ money($item->line_total) }}</strong>
                        </div>
                    @endforeach

                    <dl class="fc-summary__rows mt-3">
                        <dt>Subtotal</dt>
                        <dd>{{ money($quotation->subtotal) }}</dd>

                        @if ($quotation->discount > 0)
                            <dt class="is-discount">Discount</dt>
                            <dd class="is-discount">− {{ money($quotation->discount) }}</dd>
                        @endif

                        <dt>Delivery</dt>
                        <dd>{{ $quotation->delivery_charge > 0 ? money($quotation->delivery_charge) : 'Free' }}</dd>
                    </dl>

                    <div class="fc-summary__total">
                        <span>Total</span>
                        <strong>{{ money($quotation->grand_total) }}</strong>
                    </div>

                    @if ($quotation->tax_total > 0)
                        <p class="fc-summary__tax mb-0">Includes {{ money($quotation->tax_total) }} GST.</p>
                    @endif

                    @if ($quotation->delivery_days)
                        <p class="small text-muted mt-2 mb-0">
                            <i class="bi bi-truck me-1"></i>
                            About {{ $quotation->delivery_days }} days to deliver once the order is confirmed.
                        </p>
                    @endif
                </div>

                @if ($quotation->terms)
                    <div class="fc-panel">
                        <h2 class="fc-panel__title">Terms</h2>
                        <p class="small text-muted mb-0" style="white-space: pre-line">{{ $quotation->terms }}</p>
                    </div>
                @endif
            </div>

            {{-- --------------------------------------------------- answer --}}
            <div class="col-lg-4">
                <div class="fc-panel">
                    <h2 class="fc-panel__title">Your answer</h2>

                    @if ($quotation->isAnswerable())
                        @if ($quotation->valid_until)
                            <p class="small text-muted">
                                This holds until <strong>{{ $quotation->valid_until->format('D, d M Y') }}</strong>
                                @if ($quotation->days_left !== null)
                                    — {{ $quotation->days_left }} day(s) left.
                                @endif
                            </p>
                        @endif

                        <form method="POST" action="{{ route('quotation.accept', $quotation->public_token) }}" class="mb-3">
                            @csrf
                            <label for="customer_note" class="form-label">Anything to add?</label>
                            <textarea id="customer_note" name="customer_note" rows="2" maxlength="1000"
                                      class="form-control form-control-sm mb-2"
                                      placeholder="Delivery timing, a change you would like"></textarea>

                            <button class="btn btn-navy w-100">
                                <i class="bi bi-check-lg"></i> Accept this quotation
                            </button>
                        </form>

                        <p class="small text-muted">
                            Nothing is charged when you accept. We will confirm everything with you and
                            send an invoice once the order is placed.
                        </p>

                        <hr>

                        <button class="btn btn-sm btn-outline-secondary w-100" type="button"
                                data-bs-toggle="collapse" data-bs-target="#declineQuotation"
                                aria-expanded="false" aria-controls="declineQuotation">
                            Not for me
                        </button>

                        <div class="collapse mt-2" id="declineQuotation">
                            <form method="POST" action="{{ route('quotation.decline', $quotation->public_token) }}">
                                @csrf
                                <label for="decline_reason" class="form-label">
                                    What put you off? <span class="text-muted">(optional)</span>
                                </label>
                                <input type="text" id="decline_reason" name="decline_reason" maxlength="300"
                                       class="form-control form-control-sm mb-2"
                                       placeholder="Price, timing, found something else…">
                                <button class="btn btn-sm btn-outline-danger w-100">Decline</button>
                            </form>
                        </div>
                    @elseif ($quotation->status === 'accepted' || $quotation->status === 'converted')
                        <div class="alert alert-success small mb-0">
                            <i class="bi bi-check-circle me-1"></i>
                            You accepted this{{ $quotation->accepted_at ? ' on '.$quotation->accepted_at->format('d M Y') : '' }}.
                            We will be in touch to arrange everything.
                        </div>
                    @elseif ($quotation->status === 'declined')
                        <div class="alert alert-light border small mb-0">
                            You declined this quotation. If you change your mind, just get in touch —
                            we would be glad to quote again.
                        </div>
                    @elseif ($quotation->hasLapsed())
                        <div class="alert alert-warning small">
                            This quotation lapsed on {{ $quotation->valid_until->format('d M Y') }}.
                            Prices may have moved since.
                        </div>
                        <a href="{{ route('enquiry.create') }}" class="btn btn-navy w-100">Ask for a fresh quote</a>
                    @else
                        <p class="small text-muted mb-0">This quotation is no longer open for an answer.</p>
                    @endif
                </div>

                <div class="fc-panel mt-3">
                    <h2 class="fc-panel__title">Questions?</h2>
                    <ul class="fc-contact-list">
                        <li>
                            <i class="bi bi-telephone-fill"></i>
                            <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '')) }}">{{ settings('company_phone') }}</a>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill"></i>
                            <a href="mailto:{{ settings('company_email') }}">{{ settings('company_email') }}</a>
                        </li>
                    </ul>
                    <p class="small text-muted mb-0">Quote {{ $quotation->number }} when you get in touch.</p>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
