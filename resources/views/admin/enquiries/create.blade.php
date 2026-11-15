@extends('admin.layouts.app')

@section('title', 'Log an enquiry')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.enquiries.index') }}">Enquiries</a></li>
    <li class="breadcrumb-item active" aria-current="page">Log one</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card icon="bi-telephone-inbound" title="Log an enquiry"
                      subtitle="For something that came in by phone, WhatsApp or at the counter">

            <form method="POST" action="{{ route('admin.enquiries.store') }}">
                @csrf

                <div class="row g-2">
                    <div class="col-md-6">
                        <label for="type" class="form-label">What is it about? <span class="text-danger">*</span></label>
                        <select id="type" name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="source" class="form-label">How did it come in? <span class="text-danger">*</span></label>
                        <select id="source" name="source" class="form-select form-select-sm @error('source') is-invalid @enderror" required>
                            @foreach ($sources as $value => $label)
                                <option value="{{ $value }}" @selected(old('source', 'phone') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('source') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="name" class="form-label">Their name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" required maxlength="120" value="{{ old('name') }}"
                               class="form-control form-control-sm @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" required maxlength="20" value="{{ old('phone') }}"
                               class="form-control form-control-sm @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" required maxlength="255" value="{{ old('email') }}"
                               class="form-control form-control-sm @error('email') is-invalid @enderror">
                        <div class="form-text" style="font-size:.72rem">
                            Needed to send a quotation, so ask for it while they are on the line.
                        </div>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="city" class="form-label">City</label>
                        <input type="text" id="city" name="city" maxlength="120" value="{{ old('city') }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                        <label for="pincode" class="form-label">Pincode</label>
                        <input type="text" id="pincode" name="pincode" maxlength="6" inputmode="numeric"
                               value="{{ old('pincode') }}"
                               class="form-control form-control-sm @error('pincode') is-invalid @enderror">
                        @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" id="subject" name="subject" maxlength="180" value="{{ old('subject') }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-12">
                        <label for="message" class="form-label">What they want <span class="text-danger">*</span></label>
                        <textarea id="message" name="message" rows="4" required maxlength="5000"
                                  class="form-control form-control-sm @error('message') is-invalid @enderror"
                                  placeholder="Write it down while it is fresh — sizes, materials, where it has to fit">{{ old('message') }}</textarea>
                        @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="quantity" class="form-label">How many</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="100000"
                               value="{{ old('quantity') }}" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                        <label for="budget" class="form-label">Budget</label>
                        <input type="number" step="0.01" min="0" id="budget" name="budget"
                               value="{{ old('budget') }}" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                        <label for="required_by" class="form-label">Needed by</label>
                        <input type="date" id="required_by" name="required_by" value="{{ old('required_by') }}"
                               class="form-control form-control-sm">
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-primary">Save it</button>
                    <a href="{{ route('admin.enquiries.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
                </div>

                <p class="small text-muted mt-2 mb-0">
                    It will be assigned to you, and no acknowledgement email goes out — they already
                    know you have it.
                </p>
            </form>
        </x-admin.card>
    </div>
</div>

@endsection
