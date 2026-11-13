<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ProductBrowser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(protected ProductBrowser $browser) {}

    /**
     * All top-level categories with their sub-categories.
     */
    public function index(): View
    {
        $categories = Category::query()
            ->active()
            ->roots()
            ->ordered()
            ->with(['children' => fn ($q) => $q->active()->ordered()->withCount('activeProducts')])
            ->withCount('activeProducts')
            ->get();

        return view('front.categories', compact('categories'));
    }

    /**
     * One category: its own products plus everything in its sub-categories.
     */
    public function show(Request $request, string $slug): View
    {
        $request->validate(ProductBrowser::rules());

        $category = Category::query()
            ->active()
            ->where('slug', $slug)
            ->with(['parent', 'children' => fn ($q) => $q->active()->ordered()->withCount('activeProducts')])
            ->firstOrFail();

        $products = $this->browser->query($request, $category)->paginate(12)->withQueryString();

        return view('front.category-show', [
            'category' => $category,
            'products' => $products,
            'sorts' => ProductBrowser::SORTS,
            'filters' => $this->browser->filterOptions(),
        ]);
    }
}
