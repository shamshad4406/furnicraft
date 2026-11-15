<h3 class="h6 fw-bold mb-3" style="color:var(--fc-navy)">Search engines</h3>

<div class="row g-3">
    <x-admin.switcher name="seo_allow_indexing" col="col-md-6"
                      :checked="settings()->bool('seo_allow_indexing', true)"
                      help="Off while the shop is still being built: every page then says noindex and
                            robots.txt disallows everything. Turn it on the day you go live.">
        Let search engines index the shop
    </x-admin.switcher>

    <div class="col-md-6">
        <span class="form-label d-block">Addresses search engines read</span>
        <div class="small">
            <a href="{{ route('sitemap') }}" target="_blank" rel="noopener">/sitemap.xml</a> ·
            <a href="{{ route('robots') }}" target="_blank" rel="noopener">/robots.txt</a>
        </div>
        <div class="form-text">
            Both are generated from live data — no file to regenerate after adding a product.
        </div>
    </div>

    <x-admin.input name="google_analytics_id" label="Google Analytics measurement ID"
                   :value="settings('google_analytics_id')" col="col-md-6" placeholder="G-XXXXXXXXXX"
                   help="Blank = no tracking script at all. The tag is only allowed through the
                         content-security policy while this is filled in." />

    <x-admin.input name="google_site_verification" label="Search Console verification code"
                   :value="settings('google_site_verification')" col="col-md-6"
                   help="The content value from Google's HTML-tag method, without the tag around it." />
</div>

<hr class="my-4">

<h3 class="h6 fw-bold mb-3" style="color:var(--fc-navy)">Customer reviews</h3>

<div class="row g-3">
    <x-admin.switcher name="reviews_enabled" col="col-md-6"
                      :checked="settings()->bool('reviews_enabled', true)"
                      help="Off hides the review form. Reviews already published stay on the product pages.">
        Accept reviews from customers
    </x-admin.switcher>

    <x-admin.switcher name="reviews_require_purchase" col="col-md-6"
                      :checked="settings()->bool('reviews_require_purchase', false)"
                      help="On, only an email with a delivered order for that product may review it.
                            Off, anybody may write one — nothing is published until you have read it either way.">
        Only buyers may review
    </x-admin.switcher>
</div>

@php
    $pending = \App\Models\Review::pending()->count();
    $published = \App\Models\Review::approved()->count();
@endphp

<div class="row g-3 small mt-1">
    <div class="col-md-4">
        <span class="text-muted d-block">Waiting to be read</span>
        <strong class="{{ $pending > 0 ? 'text-warning' : 'text-success' }}">
            <a href="{{ route('admin.reviews.index', ['status' => 'pending']) }}">{{ $pending }} review(s)</a>
        </strong>
    </div>
    <div class="col-md-4">
        <span class="text-muted d-block">Published</span>
        <strong><a href="{{ route('admin.reviews.index', ['status' => 'approved']) }}">{{ $published }} review(s)</a></strong>
    </div>
    <div class="col-md-4">
        <span class="text-muted d-block">Average of published</span>
        <strong>{{ number_format((float) \App\Models\Review::approved()->avg('rating'), 2) }} / 5</strong>
    </div>
</div>

<hr class="my-4">

<h3 class="h6 fw-bold mb-3" style="color:var(--fc-navy)">Mailing list</h3>

<div class="row g-3">
    <x-admin.switcher name="newsletter_enabled" col="col-md-6"
                      :checked="settings()->bool('newsletter_enabled', true)"
                      help="Off removes the subscribe form from the footer. The list itself is kept.">
        Show the subscribe form
    </x-admin.switcher>

    <x-admin.switcher name="newsletter_double_optin" col="col-md-6"
                      :checked="settings()->bool('newsletter_double_optin', true)"
                      help="On, a confirmation link is emailed and nothing counts as subscribed until
                            it is clicked. Leave it on: it is what keeps somebody else's address off your list.">
        Confirm by email first
    </x-admin.switcher>

    <x-admin.textarea name="newsletter_intro" label="Line above the subscribe form" rows="2"
                      :value="settings('newsletter_intro')"
                      help="Optional. Shown in the footer, under the heading." />
</div>

@php $list = app(\App\Services\NewsletterService::class)->counts(); @endphp

<div class="row g-3 small mt-1">
    <div class="col-md-4">
        <span class="text-muted d-block">Subscribed</span>
        <strong><a href="{{ route('admin.newsletter.index', ['status' => 'subscribed']) }}">{{ number_format($list['subscribed']) }}</a></strong>
    </div>
    <div class="col-md-4">
        <span class="text-muted d-block">Yet to confirm</span>
        <strong class="{{ $list['pending'] > 0 ? 'text-warning' : '' }}">
            <a href="{{ route('admin.newsletter.index', ['status' => 'pending']) }}">{{ number_format($list['pending']) }}</a>
        </strong>
    </div>
    <div class="col-md-4">
        <span class="text-muted d-block">Banners on screen now</span>
        <strong><a href="{{ route('admin.banners.index', ['flag' => 'live']) }}">{{ \App\Models\Banner::live()->count() }}</a></strong>
    </div>
</div>
