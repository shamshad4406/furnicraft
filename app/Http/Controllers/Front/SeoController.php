<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\SeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The files search engines ask for, and the banner click counter.
 *
 * Both served by the app rather than written to disk: a sitemap generated on
 * request cannot go stale, and this shop's catalogue is small enough that
 * building it per request costs nothing worth caching around.
 */
class SeoController extends Controller
{
    public function __construct(protected SeoService $seo) {}

    public function sitemap(): Response
    {
        $urls = $this->seo->sitemapUrls();

        $xml = view('front.seo.sitemap', compact('urls'))->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // A sitemap is cheap to rebuild but asked for often.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * robots.txt, built from what the shop actually wants indexed.
     *
     * Everything behind a sign-in or carrying a one-off token is excluded:
     * an order tracking link in a search result is somebody's address on the
     * open web.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
        ];

        if (settings()->bool('seo_allow_indexing', true)) {
            $lines = array_merge($lines, [
                'Allow: /',
                '',
                '# Private, or addressed by a one-off token.',
                'Disallow: /admin',
                'Disallow: /account',
                'Disallow: /checkout',
                'Disallow: /cart',
                'Disallow: /wishlist',
                'Disallow: /track-order',
                'Disallow: /quotation',
                'Disallow: /enquiry/sent',
                'Disallow: /newsletter',
                'Disallow: /*?q=',
            ]);
        } else {
            // A staging site that is indexed is worse than one nobody finds.
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Count a banner click, then send them where it points.
     *
     * A redirect rather than a background call, so the count is of people
     * who actually arrived rather than of scripts that loaded the page.
     */
    public function banner(Banner $banner): RedirectResponse
    {
        $banner->increment('click_count');

        $target = $banner->cta_url ?: route('products');

        // Only ever our own site: an open redirect on a shop is a phishing
        // link with the shop's name on it.
        if (! str_starts_with($target, url('/')) && ! str_starts_with($target, '/')) {
            $target = route('products');
        }

        return redirect()->to($target);
    }
}
