@extends('admin.layouts.app')

@section('title', 'Enquiry '.$enquiry->reference)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.enquiries.index') }}">Enquiries</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $enquiry->reference }}</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card icon="bi-chat-left-text" title="{{ $enquiry->reference }}"
                      subtitle="Received {{ $enquiry->created_at->format('d M Y, H:i') }} · {{ \App\Models\Enquiry::SOURCES[$enquiry->source] }}">
            <x-slot:actions>
                <span class="badge text-bg-{{ $enquiry->status_badge }}">{{ $enquiry->status_label }}</span>
                <span class="badge text-bg-light border">{{ $enquiry->type_label }}</span>
            </x-slot:actions>

            <div class="row g-3">
                <div class="col-md-5">
                    <div class="ad-detail__label">From</div>
                    <div class="fw-semibold">{{ $enquiry->name }}</div>
                    <div class="small"><a href="mailto:{{ $enquiry->email }}">{{ $enquiry->email }}</a></div>
                    <div class="small"><a href="tel:{{ $enquiry->phone }}">{{ $enquiry->phone }}</a></div>
                    @if ($enquiry->city || $enquiry->pincode)
                        <div class="small text-muted">{{ collect([$enquiry->city, $enquiry->pincode])->filter()->implode(' — ') }}</div>
                    @endif
                    @if ($enquiry->customer)
                        <span class="badge text-bg-light border mt-1">Registered customer</span>
                    @endif
                </div>

                <div class="col-md-4">
                    <div class="ad-detail__label">What they want</div>
                    @if ($enquiry->product)
                        <div class="small fw-semibold">{{ $enquiry->product->name }}</div>
                        <div class="small text-muted">SKU {{ $enquiry->product->sku }} · {{ money($enquiry->product->price) }}</div>
                    @else
                        <div class="small text-muted">Not a catalogue item</div>
                    @endif
                    @if ($enquiry->quantity)
                        <div class="small">Quantity: <strong>{{ $enquiry->quantity }}</strong></div>
                    @endif
                </div>

                <div class="col-md-3">
                    <div class="ad-detail__label">Budget &amp; timing</div>
                    <div class="small">{{ $enquiry->budget ? money($enquiry->budget) : 'Not said' }}</div>
                    @if ($enquiry->required_by)
                        <div class="small">Needed by <strong>{{ $enquiry->required_by->format('d M Y') }}</strong></div>
                    @endif
                </div>
            </div>

            @if ($enquiry->subject)
                <h3 class="h6 mt-3 mb-1">{{ $enquiry->subject }}</h3>
            @endif

            <div class="ad-enquiry-message">{{ $enquiry->message }}</div>

            @if ($enquiry->attachments->isNotEmpty())
                <div class="ad-detail__label mt-3">What they sent</div>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach ($enquiry->attachments as $file)
                        <a href="{{ $file->url }}" target="_blank" rel="noopener noreferrer" class="ad-attachment">
                            <img src="{{ $file->url }}" alt="{{ $file->original_name }}" loading="lazy">
                            <span>{{ $file->readable_size }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($enquiry->close_reason)
                <div class="alert alert-light border small mt-3 mb-0">
                    <strong>Closed:</strong> {{ $enquiry->close_reason }}
                </div>
            @endif
        </x-admin.card>

        {{-- --------------------------------------------------- the thread --}}
        <x-admin.card icon="bi-chat-dots" title="Conversation"
                      subtitle="{{ $enquiry->replies->count() }} entry(s)" class="mt-3">
            @forelse ($enquiry->replies as $reply)
                <div class="ad-reply {{ $reply->isInternal() ? 'is-internal' : '' }}">
                    <div class="small">
                        <strong>{{ $reply->author }}</strong>
                        <span class="badge text-bg-light border ms-1">{{ $reply->channel_label }}</span>
                        @if ($reply->emailed_at)
                            <span class="badge text-bg-light border" title="Emailed to the customer">
                                <i class="bi bi-envelope-check"></i>
                            </span>
                        @endif
                        <span class="text-muted ms-1" style="font-size:.72rem">
                            {{ $reply->created_at->format('d M Y, H:i') }}
                        </span>
                    </div>
                    <div class="ad-reply__body">{{ $reply->body }}</div>
                </div>
            @empty
                <p class="small text-muted mb-0">Nobody has replied yet.</p>
            @endforelse

            @can('enquiries.edit')
                <hr>
                <form method="POST" action="{{ route('admin.enquiries.reply', $enquiry) }}">
                    @csrf

                    <label for="body" class="form-label">Add to the thread</label>
                    <textarea id="body" name="body" rows="4" maxlength="5000" required
                              class="form-control form-control-sm @error('body') is-invalid @enderror"
                              placeholder="What you told them, or what you want the next person to know">{{ old('body') }}</textarea>
                    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    <div class="d-flex gap-2 align-items-center mt-2">
                        <select name="channel" class="form-select form-select-sm" style="width:auto" aria-label="How">
                            @foreach ($channels as $value => $label)
                                <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-primary">Save</button>
                        <span class="small text-muted">
                            "Emailed" sends it; the rest only record what already happened.
                        </span>
                    </div>
                </form>
            @endcan
        </x-admin.card>
    </div>

    {{-- ------------------------------------------------------ the actions --}}
    <div class="col-xl-4">
        @if ($enquiry->quotations->isNotEmpty())
            <x-admin.card icon="bi-file-earmark-text" title="Quotations">
                @foreach ($enquiry->quotations as $quotation)
                    <div class="d-flex justify-content-between align-items-start gap-2 py-1">
                        <div>
                            <a href="{{ route('admin.quotations.show', $quotation) }}" class="fw-semibold text-decoration-none">
                                {{ $quotation->number }}
                            </a>
                            <div class="small text-muted">{{ money($quotation->grand_total) }}</div>
                        </div>
                        <span class="badge text-bg-{{ $quotation->status_badge }}">{{ $quotation->status_label }}</span>
                    </div>
                @endforeach
            </x-admin.card>
        @endif

        @can('quotations.create')
            @if ($enquiry->isOpen())
                <x-admin.card icon="bi-file-earmark-plus" title="Price it up"
                              class="{{ $enquiry->quotations->isNotEmpty() ? 'mt-3' : '' }}">
                    <p class="small text-muted">
                        Starts a quotation with their details and, if they asked about a product,
                        that product as the first line.
                    </p>
                    <a href="{{ route('admin.quotations.create', ['enquiry' => $enquiry->public_token]) }}"
                       class="btn btn-sm btn-primary w-100">New quotation</a>
                </x-admin.card>
            @endif
        @endcan

        @can('enquiries.edit')
            <x-admin.card icon="bi-person-check" title="Who is handling it" class="mt-3">
                <form method="POST" action="{{ route('admin.enquiries.assign', $enquiry) }}">
                    @csrf @method('PATCH')
                    <select name="assigned_to" class="form-select form-select-sm mb-2" aria-label="Assign to">
                        <option value="">Nobody yet</option>
                        @foreach ($staff as $person)
                            <option value="{{ $person->id }}" @selected($enquiry->assigned_to == $person->id)>
                                {{ $person->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary w-100">Assign</button>
                </form>
            </x-admin.card>

            <x-admin.card icon="bi-sticky" title="Internal note" class="mt-3">
                <form method="POST" action="{{ route('admin.enquiries.note', $enquiry) }}">
                    @csrf @method('PATCH')
                    <textarea name="admin_note" rows="3" maxlength="1000"
                              class="form-control form-control-sm mb-2"
                              placeholder="Never shown to the customer">{{ old('admin_note', $enquiry->admin_note) }}</textarea>
                    <button class="btn btn-sm btn-outline-secondary w-100">Save note</button>
                </form>
            </x-admin.card>

            @if ($enquiry->isOpen())
                <x-admin.card icon="bi-archive" title="Finish with it" class="mt-3">
                    <form method="POST" action="{{ route('admin.enquiries.close', $enquiry) }}">
                        @csrf @method('PATCH')

                        <label for="close_reason" class="form-label">Why</label>
                        <input type="text" id="close_reason" name="close_reason" maxlength="300"
                               class="form-control form-control-sm mb-2"
                               placeholder="Answered, not going ahead, duplicate…">

                        <div class="d-flex gap-2">
                            <button name="status" value="closed" class="btn btn-sm btn-outline-secondary flex-grow-1">
                                Close
                            </button>
                            <button name="status" value="spam" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Mark this as spam and hide it from the inbox?">
                                Spam
                            </button>
                        </div>
                    </form>
                </x-admin.card>
            @else
                <x-admin.card icon="bi-arrow-counterclockwise" title="Closed" class="mt-3">
                    <p class="small text-muted">
                        {{ $enquiry->status_label }}@if ($enquiry->closed_at) on {{ $enquiry->closed_at->format('d M Y') }}@endif.
                    </p>
                    <form method="POST" action="{{ route('admin.enquiries.reopen', $enquiry) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-secondary w-100">Reopen</button>
                    </form>
                </x-admin.card>
            @endif
        @endcan
    </div>
</div>

@endsection
