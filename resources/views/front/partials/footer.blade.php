@php
    $footerPages = cache()->remember('front.footer.pages', now()->addMinutes(10), function () {
        return \App\Models\CmsPage::query()->active()->inFooter()->ordered()->get(['title', 'slug']);
    });

    $socials = [
        'social_facebook' => 'bi-facebook',
        'social_youtube' => 'bi-youtube',
        'social_instagram' => 'bi-instagram',
        'social_linkedin' => 'bi-linkedin',
    ];
@endphp

<footer class="fc-footer">
    <div class="container-fc">
        <div class="row g-4">

            <div class="col-lg-3 col-md-6">
                <div class="fc-logo mb-3">
                    <img src="{{ setting_file('company_logo') ?? asset('images/logo.svg') }}" alt="{{ settings('company_name', 'FurniCraft') }}">
                    <span>
                        <span class="fc-logo__name d-block">Furni<span>Craft</span></span>
                        <span class="fc-logo__tag d-block">{{ settings('company_tagline', 'Style • Comfort • Durability') }}</span>
                    </span>
                </div>
                <p class="mb-0">{{ settings('company_about', 'Your trusted partner for stylish and durable furniture solutions.') }}</p>
            </div>

            <div class="col-lg-2 col-md-6">
                <h5>Quick Links</h5>
                <ul class="list-unstyled fc-footer__links mb-0">
                    <li><i class="bi bi-chevron-right"></i><a href="{{ route('home') }}">Home</a></li>
                    <li><i class="bi bi-chevron-right"></i><a href="{{ route('categories') }}">Categories</a></li>
                    <li><i class="bi bi-chevron-right"></i><a href="{{ route('products') }}">Products</a></li>
                    <li><i class="bi bi-chevron-right"></i><a href="{{ route('track.form') }}">Track Order</a></li>
                    @foreach ($footerPages->take(4) as $footerPage)
                        <li>
                            <i class="bi bi-chevron-right"></i>
                            <a href="{{ route('page', $footerPage->slug) }}">{{ $footerPage->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5>Contact Us</h5>
                <ul class="list-unstyled fc-footer__contact mb-0">
                    <li>
                        <i class="bi bi-telephone-fill"></i>
                        <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '')) }}">{{ settings('company_phone', '+91 98765 43210') }}</a>
                    </li>
                    <li>
                        <i class="bi bi-envelope-fill"></i>
                        <a href="mailto:{{ settings('company_email') }}">{{ settings('company_email', 'info@furnicraft.in') }}</a>
                    </li>
                    <li>
                        <i class="bi bi-geo-alt-fill"></i>
                        <span>{!! nl2br(e(settings('company_address', "123 Furniture Street,\nNew Delhi - 110001"))) !!}</span>
                    </li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6">
                <h5>Follow Us</h5>
                <div class="fc-footer__social">
                    @foreach ($socials as $key => $icon)
                        @if (settings($key))
                            <a href="{{ settings($key) }}" target="_blank" rel="noopener noreferrer"
                               aria-label="{{ ucfirst(str_replace('social_', '', $key)) }}">
                                <i class="bi {{ $icon }}"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                @if (settings()->bool('newsletter_enabled', true))
                    <div class="fc-footer__subscribe">
                        <h5>Subscribe for Updates</h5>

                        @if (filled(settings('newsletter_intro')))
                            <p class="small mb-2" style="opacity:.75">{{ settings('newsletter_intro') }}</p>
                        @endif

                        <form class="d-flex flex-column gap-2" method="POST" action="{{ route('newsletter.subscribe') }}">
                            @csrf
                            <input type="hidden" name="source" value="footer">

                            {{-- Hidden from people, irresistible to bots. --}}
                            <div class="fc-hp" aria-hidden="true">
                                <label for="footerWebsite">Leave this empty</label>
                                <input type="text" id="footerWebsite" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   placeholder="Enter your email" aria-label="Email address"
                                   value="{{ old('email') }}" required>
                            @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                            <button type="submit" class="btn">Subscribe</button>
                        </form>

                        @if (settings()->bool('newsletter_double_optin', true))
                            <p class="small mt-2 mb-0" style="opacity:.7">
                                We send a confirmation link first — nothing until you click it.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="fc-footer__bottom">
            <span>{{ settings('company_copyright', '© '.date('Y').' FurniCraft. All Rights Reserved.') }}</span>
            <span class="fc-footer__policies">
                @foreach ($footerPages->slice(4) as $footerPage)
                    @if (! $loop->first)<span class="opacity-50">|</span>@endif
                    <a href="{{ route('page', $footerPage->slug) }}">{{ $footerPage->title }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
