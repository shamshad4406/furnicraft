<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\CatalogService;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CategoryController extends Controller implements HasMiddleware
{
    public function __construct(
        protected CatalogService $catalog,
        protected ImageService $images,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:categories.view', only: ['index']),
            new Middleware('permission:categories.create', only: ['create', 'store']),
            new Middleware('permission:categories.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:categories.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        // The tree is two levels deep (parent / child), so eager-loading the
        // children is enough for the whole screen.
        $categories = Category::query()
            ->with(['children' => fn ($q) => $q->withCount('products')])
            ->withCount('products')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('slug', 'like', $term));
            }, fn ($query) => $query->roots())
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->ordered()
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true, 'sort_order' => 0]),
            'parents' => Category::parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('categories', $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['image']);

        $category = new Category($data);

        if ($request->hasFile('image')) {
            $category->image = $this->images->storeSingle($request->file('image'), 'categories', 800, $data['slug']);
        }

        $category->save();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category "'.$category->name.'" created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('categories', $data['name'], $category->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['image']);

        $category->fill($data);

        if ($request->hasFile('image')) {
            $old = $category->image;
            $category->image = $this->images->storeSingle($request->file('image'), 'categories', 800, $data['slug']);
            $this->images->delete($old);
        }

        $category->save();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category "'.$category->name.'" updated.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        return back()->with('success', $category->name.' is now '.($category->is_active ? 'active' : 'hidden').'.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Move the products out of "'.$category->name.'" before deleting it.');
        }

        if ($category->children()->exists()) {
            return back()->with('error', 'Delete or move the sub-categories of "'.$category->name.'" first.');
        }

        $name = $category->name;

        $this->images->delete($category->image);
        $this->catalog->releaseUniqueColumns($category, ['slug']);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category "'.$name.'" deleted.');
    }
}
