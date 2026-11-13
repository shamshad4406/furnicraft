<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\RecentlyViewedService;
use App\Services\ShowcaseCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected ShowcaseCatalog $showcase,
        protected RecentlyViewedService $recentlyViewed,
    ) {}

    public function index(Request $request): View
    {
        return view('front.home', [
            // Banner slides and the trust strip are static content until the
            // banner module arrives in Phase 8.
            'slides' => $this->showcase->slides(),
            'usps' => $this->showcase->usps(),
            'testimonials' => $this->showcase->testimonials(),

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
    protected function row(callable $scope, int $limit = 5)
    {
        $query = Product::query()->active()->with(['images', 'category:id,name']);

        $scope($query);

        return $query->limit($limit)->get();
    }
}
