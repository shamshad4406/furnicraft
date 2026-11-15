@extends('front.layouts.app')

@section('title', 'Enquiry '.$enquiry->reference.' received')
@section('robots', 'noindex, nofollow')

@section('content')

<section class="fc-section">
    <div class="container-fc">
        <div class="fc-confirm">
            <div class="fc-confirm__badge"><i class="bi bi-check-lg"></i></div>

            <h1>Thank you, {{ Str::before($enquiry->name, ' ') }}</h1>

            <p class="text-muted">
                We have your enquiry. A copy has gone to <strong>{{ $enquiry->email }}</strong>.
            </p>

            <div class="fc-confirm__facts">
                <div>
                    <span class="text-muted small">Reference</span>
                    <strong>{{ $enquiry->reference }}</strong>
                </div>
                <div>
                    <span class="text-muted small">About</span>
                    <strong>{{ $enquiry->type_label }}</strong>
                </div>
                @if ($enquiry->product)
                    <div>
                        <span class="text-muted small">Product</span>
                        <strong>{{ $enquiry->product->name }}</strong>
                    </div>
                @endif
                @if ($enquiry->required_by)
                    <div>
                        <span class="text-muted small">Needed by</span>
                        <strong>{{ $enquiry->required_by->format('d M Y') }}</strong>
                    </div>
                @endif
            </div>

            <p class="text-muted small">
                Quote <strong>{{ $enquiry->reference }}</strong> if you call or write back. If it needs
                pricing, we will send you a written quotation you can accept online.
            </p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                <a href="{{ route('products') }}" class="btn btn-navy">Continue shopping</a>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">Back home</a>
            </div>
        </div>
    </div>
</section>

@endsection
