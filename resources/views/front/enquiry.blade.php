@extends('front.layouts.app')

@section('title', 'Send an enquiry')
@section('meta_description', 'Ask us about a product, request custom furniture, or get a quote for a bulk order.')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Send an enquiry</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Enquiry</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="fc-panel">
                    <h2 class="fc-panel__title">Tell us what you need</h2>

                    @if ($product)
                        <div class="fc-enquiry-product">
                            @if ($product->thumbnail_url)
                                <img src="{{ $product->thumbnail_url }}" alt="" width="64" height="64" loading="lazy">
                            @endif
                            <div>
                                <strong>{{ $product->name }}</strong>
                                <div class="small text-muted">SKU {{ $product->sku }}</div>
                                <div class="small text-muted">{{ money($product->effective_price) }}</div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('enquiry.store') }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        <input type="hidden" name="product_slug" value="{{ $product?->slug }}">

                        {{-- A bot fills everything in; a person never sees this.
                             Hidden with CSS, not type="hidden", which bots skip. --}}
                        <div class="fc-hp" aria-hidden="true">
                            <label for="website">Leave this empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="type" class="form-label">What is this about? <span class="text-danger">*</span></label>
                                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', $defaultType) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="name" class="form-label">Your name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" required maxlength="120"
                                       value="{{ old('name', $customer?->name) }}"
                                       class="form-control @error('name') is-invalid @enderror">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Mobile <span class="text-danger">*</span></label>
                                <input type="text" id="phone" name="phone" required maxlength="20" inputmode="tel"
                                       value="{{ old('phone', $customer?->phone) }}"
                                       class="form-control @error('phone') is-invalid @enderror">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" required maxlength="255"
                                       value="{{ old('email', $customer?->email) }}"
                                       class="form-control @error('email') is-invalid @enderror">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" id="city" name="city" maxlength="120" value="{{ old('city') }}"
                                       class="form-control @error('city') is-invalid @enderror">
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="pincode" class="form-label">Pincode</label>
                                <input type="text" id="pincode" name="pincode" maxlength="6" inputmode="numeric"
                                       value="{{ old('pincode') }}"
                                       class="form-control @error('pincode') is-invalid @enderror">
                                @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" id="subject" name="subject" maxlength="180"
                                       value="{{ old('subject', $product ? 'Enquiry about '.$product->name : '') }}"
                                       class="form-control @error('subject') is-invalid @enderror">
                                @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="message" class="form-label">Your message <span class="text-danger">*</span></label>
                                <textarea id="message" name="message" rows="5" required maxlength="5000"
                                          class="form-control @error('message') is-invalid @enderror"
                                          placeholder="Sizes, materials, colours, where it has to fit — whatever you already know">{{ old('message') }}</textarea>
                                @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="quantity" class="form-label">How many?</label>
                                <input type="number" id="quantity" name="quantity" min="1" max="100000"
                                       value="{{ old('quantity') }}"
                                       class="form-control @error('quantity') is-invalid @enderror">
                                @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="budget" class="form-label">Budget <span class="text-muted">(optional)</span></label>
                                <input type="number" id="budget" name="budget" step="0.01" min="0"
                                       value="{{ old('budget') }}"
                                       class="form-control @error('budget') is-invalid @enderror">
                                @error('budget') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="required_by" class="form-label">Needed by</label>
                                <input type="date" id="required_by" name="required_by" value="{{ old('required_by') }}"
                                       class="form-control @error('required_by') is-invalid @enderror">
                                @error('required_by') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="attachments" class="form-label">
                                    Photos or sketches <span class="text-muted">(up to 3)</span>
                                </label>
                                <input type="file" id="attachments" name="attachments[]" multiple
                                       accept="image/jpeg,image/png,image/webp"
                                       class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror">
                                <div class="form-text">
                                    A photo of the space, or a drawing of what you have in mind, helps us quote properly.
                                </div>
                                @error('attachments') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                @error('attachments.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-navy mt-3">
                            <i class="bi bi-send"></i> Send enquiry
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="fc-panel h-100">
                    <h2 class="fc-panel__title">Or just talk to us</h2>

                    <ul class="fc-contact-list">
                        <li>
                            <i class="bi bi-telephone-fill"></i>
                            <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '')) }}">{{ settings('company_phone') }}</a>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill"></i>
                            <a href="mailto:{{ settings('company_email') }}">{{ settings('company_email') }}</a>
                        </li>
                        @if (settings('company_whatsapp'))
                            <li>
                                <i class="bi bi-whatsapp"></i>
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', settings('company_whatsapp')) }}"
                                   target="_blank" rel="noopener noreferrer">WhatsApp us</a>
                            </li>
                        @endif
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <span style="white-space: pre-line">{{ settings('company_address') }}</span>
                        </li>
                    </ul>

                    <hr>

                    <h3 class="h6">What happens next</h3>
                    <ol class="small text-muted ps-3 mb-0">
                        <li>We email you a reference straight away.</li>
                        <li>Someone reads it and comes back to you, usually the same working day.</li>
                        <li>If it needs pricing, we send a written quotation you can accept online.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
