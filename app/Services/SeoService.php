<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The structured data and sitemap that let search engines read the shop.
 *
 * Schema.org JSON-LD rather than microdata scattered through the markup:
 * it keeps the structured description in one place where it can be checked,
 * instead of spread across attributes that quietly break when somebody
 * restyles a page.
 *
 * Only facts that are actually true go in. An `AggregateRating` with no
 * reviews behind it, or an `inStock` on something out of stock, is the kind
 * of thing that gets a shop's rich results pulled.
 */
class SeoService
{
    /**
     * The shop itself, for the homepage.
     *
     * @return array<string, mixed>
     */
    public function organisation(): array
    {
        $name = (string) settings('company_name', 'FurniCraft');

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'FurnitureStore',
            'name' => $name,
            'url' => url('/'),
            'description' => (string) settings('company_about', ''),
        ];

        if ($logo = setting_file('company_logo')) {
            $data['logo'] = $logo;
            $data['image'] = $logo;
        }

        if ($phone = settings('company_phone')) {
            $data['telephone'] = $phone;
        }

        if ($email = settings('company_email')) {
            $data['email'] = $email;
        }

        if ($address = settings('company_address')) {
            $data['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => trim(preg_replace('/\s+/', ' ', (string) $address)),
                'addressCountry' => 'IN',
            ];
        }

        $socials = collect([
            settings('social_facebook'),
            settings('social_instagram'),
            settings('social_youtube'),
            settings('social_linkedin'),
        ])->filter(fn ($url) => filled($url) && $url !== 'https://facebook.com/' && str_contains((string) $url, '://'))
            ->values();

        if ($socials->isNotEmpty()) {
            $data['sameAs'] = $socials->all();
        }

        return $data;
    }

    /**
     * One product.
     *
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 500),
            'url' => route('product.show', $product->slug),
        ];

        if ($image = $product->thumbnail_url) {
            $data['image'] = $image;
        }

        if ($product->brand) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }

        if ($product->material) {
            $data['material'] = $product->material;
        }

        if ($product->color) {
            $data['color'] = $product->color;
        }

        $data['offers'] = [
            '@type' => 'Offer',
            'url' => route('product.show', $product->slug),
            'priceCurrency' => 'INR',
            'price' => number_format((float) $product->effective_price, 2, '.', ''),
            // Honest: what the stock actually says, not a hopeful default.
            'availability' => $product->total_stock > 0
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', 'name' => settings('company_name', 'FurniCraft')],
        ];

        // Only claimed when there are real approved reviews behind it.
        if ($product->rating_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) round((float) $product->rating_average, 1),
                'reviewCount' => (int) $product->rating_count,
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        return $data;
    }

    /**
     * The trail above the page title.
     *
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /**
     * Everything worth indexing, for sitemap.xml.
     *
     * Drafts, out-of-catalogue pages and anything behind a sign-in are left
     * out: a sitemap full of 404s and redirects is worse than none.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sitemapUrls(): Collection
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('products'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('categories'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('enquiry.create'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ]);

        Category::where('is_active', true)->orderBy('id')->chunk(200, function ($categories) use ($urls) {
            foreach ($categories as $category) {
                $urls->push([
                    'loc' => route('category.show', $category->slug),
                    'lastmod' => $category->updated_at?->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                ]);
            }
        });

        Product::where('status', 'active')->orderBy('id')->chunk(200, function ($products) use ($urls) {
            foreach ($products as $product) {
                $urls->push([
                    'loc' => route('product.show', $product->slug),
                    'lastmod' => $product->updated_at?->toAtomString(),
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                ]);
            }
        });

        CmsPage::where('is_active', true)->orderBy('id')->chunk(100, function ($pages) use ($urls) {
            foreach ($pages as $page) {
                $urls->push([
                    'loc' => route('page', $page->slug),
                    'lastmod' => $page->updated_at?->toAtomString(),
                    'priority' => '0.4',
                    'changefreq' => 'monthly',
                ]);
            }
        });

        return $urls;
    }
}
