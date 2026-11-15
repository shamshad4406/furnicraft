@extends('admin.layouts.app')

@section('title', 'New quotation')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.quotations.index') }}">Quotations</a></li>
    <li class="breadcrumb-item active" aria-current="page">New</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card icon="bi-file-earmark-plus" title="New quotation"
                      subtitle="Lines are added once it exists">
            @if ($enquiry)
                <div class="alert alert-light border small">
                    <i class="bi bi-chat-left-text me-1"></i>
                    Against enquiry
                    <a href="{{ route('admin.enquiries.show', $enquiry) }}">{{ $enquiry->reference }}</a>
                    @if ($enquiry->product)
                        — {{ $enquiry->product->name }} will be added as the first line.
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('admin.quotations.store') }}">
                @csrf
                <input type="hidden" name="enquiry_token" value="{{ $enquiry?->public_token }}">

                @include('admin.quotations._details-form', ['quotation' => null])

                <button class="btn btn-sm btn-primary mt-3">Create and add lines</button>
                <a href="{{ route('admin.quotations.index') }}" class="btn btn-sm btn-outline-secondary mt-3">Cancel</a>
            </form>
        </x-admin.card>
    </div>

    @if ($enquiry)
        <div class="col-xl-4">
            <x-admin.card icon="bi-chat-quote" title="What they asked for">
                <div class="ad-enquiry-message">{{ $enquiry->message }}</div>

                <dl class="fc-summary__rows mt-3 mb-0">
                    @if ($enquiry->quantity)
                        <dt>Quantity</dt><dd>{{ $enquiry->quantity }}</dd>
                    @endif
                    @if ($enquiry->budget)
                        <dt>Budget</dt><dd>{{ money($enquiry->budget) }}</dd>
                    @endif
                    @if ($enquiry->required_by)
                        <dt>Needed by</dt><dd>{{ $enquiry->required_by->format('d M Y') }}</dd>
                    @endif
                </dl>
            </x-admin.card>
        </div>
    @endif
</div>

@endsection
