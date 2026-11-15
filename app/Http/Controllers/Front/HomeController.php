<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Services\RecentlyViewedService;
use App\Services\ReviewService;
use App\Services\ShowcaseCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected ShowcaseCatalog $showcase,
        protected RecentlyViewedService $recentlyViewed,
        protected ReviewService $reviews,
    ) {}

    public function index(Request $request): View
    {
        // Likewise testimonials: real approved reviews once staff have
        // featured some, the written-in ones until then.
        $featuredReviews = $this->reviews->featured();

        return view('front.home', [
            'slides' => $this->slides(),
            'usps' => $this->showcase->usps(),
            'reviews' => $featuredReviews,
            'testimonials' => $featuredReviews->isEmpty() ? $this->showcase->testimonials() : [],
            'promos' => Banner::live('promo')->get(),

            'categories' => Category::query()->active()->roots()->ordered()->limit(6)->get(),

            // Oldest-first within sort_order, so the row stays stable as new
            // products are added; staff control the order with sort_order.
            'featured' => $this->row(fn ($q) => $q->featured()->orderBy('sort_order')->orderBy('id')),

            'bestSellers' => $this->row(fn ($q) => $q->where('is_best_seller', true)->orderBy('sort_order')->orderBy('id')),

            'newArrivals' => $this->row(fn ($q) => $q->where('is_new_arrival', true)->orderByDesc('id')),

            // NULLIF guards the division: a zero-priced row would otherwise
            // divide by zero, which errors under strict SQL modes.
            'offers' => $this->row(fn ($q) => $q->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'price')
                ->orderByRaw('(price - sale_price) / NULLIF(price, 0) DESC')),

            'recentlyViewed' => $this->recentlyViewed->products($request),
        ]);
    }

    /**
     * One homepage row of active products.
     */
    /**
     * The hero slides, whatever they are made of.
     *
     * Admin-managed banners when there are any, the built-in slides when
     * there are not, normalised to one shape so the view has a single loop —
     * a brand-new install still has a homepage, and nobody has to keep two
     * pieces of markup in step.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function slides()
    {
        $banners = Banner::live('hero')->get();

        if ($banners->isNotEmpty()) {
            return $banners->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'highlight' => $banner->highlight,
                'subtitle' => $banner->subtitle,
                'cta' => $banner->cta_label,
                // Through the click counter, so staff can see which banner
                // actually earns its place.
                'url' => $banner->cta_url ? route('banner.click', $banner) : route('products'),
                'image' => $banner->image_url,
                'mobile_image' => $banner->mobile_image_url,
                'alt' => $banner->image_alt ?: trim($banner->title.' '.$banner->highlight),
            ]);
        }

        return collect($this->showcase->slides())->map(fn (array $slide) => [
            'id' => null,
            'title' => $slide['title'],
            'highlight' => $slide['highlight'],
            'subtitle' => $slide['subtitle'],
            'cta' => $slide['cta'],
            'url' => route('products'),
            'image' => asset($slide['image']),
            'mobile_image' => asset($slide['image']),
            'alt' => $slide['title'].' '.$slide['highlight'],
        ]);
    }

    protected function row(callable $scope, int $limit = 5)
    {
        $query = Product::query()->active()->with(['images', 'category:id,name']);

        $scope($query);

        return $query->limit($limit)->get();
    }
}
