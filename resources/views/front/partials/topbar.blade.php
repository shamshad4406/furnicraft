<div class="fc-topbar">
    <div class="container-fc">
        <div class="fc-topbar__inner">
            <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '')) }}" class="d-inline-flex align-items-center gap-2">
                <i class="bi bi-telephone-fill"></i>
                <span>{{ settings('company_phone', '+91 98765 43210') }}</span>
            </a>

            <a href="mailto:{{ settings('company_email') }}" class="d-none d-sm-inline-flex align-items-center gap-2">
                <i class="bi bi-envelope-fill"></i>
                <span>{{ settings('company_email', 'info@furnicraft.in') }}</span>
            </a>

            <span class="fc-topbar__divider d-none d-lg-inline">|</span>

            <span class="d-none d-lg-inline">{{ settings('company_headline', 'Premium Quality Furniture for a Better Living') }}</span>

            <div class="ms-auto d-flex align-items-center gap-3">
                @auth('customer')
                    <span class="d-none d-sm-inline">Hi, {{ Str::before(auth('customer')->user()->name, ' ') }}</span>
                    <form method="POST" action="{{ route('customer.logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 border-0 align-baseline"
                                style="color:inherit;font-size:inherit;text-decoration:none">Logout</button>
                    </form>
                @else
                    <a href="{{ route('page', 'contact-us') }}">Enquiry</a>
                    <span class="fc-topbar__divider">|</span>
                    <a href="{{ route('page', 'contact-us') }}">Contact Us</a>
                @endauth
            </div>
        </div>
    </div>
</div>
