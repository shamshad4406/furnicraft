<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Services\CatalogService;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BrandController extends Controller implements HasMiddleware
{
    public function __construct(
        protected CatalogService $catalog,
        protected ImageService $images,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:brands.view', only: ['index']),
            new Middleware('permission:brands.create', only: ['create', 'store']),
            new Middleware('permission:brands.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:brands.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $brands = Brand::query()
            ->withCount('products')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->ordered()
            ->paginate(12)
            ->withQueryString();

        return view('admin.brands.index', compact('brands'));
    }

    public function create(): View
    {
        return view('admin.brands.form', ['brand' => new Brand(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('brands', $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['logo']);

        $brand = new Brand($data);

        if ($request->hasFile('logo')) {
            $brand->logo = $this->images->storeSingle($request->file('logo'), 'brands', 400, $data['slug']);
        }

        $brand->save();

        return redirect()->route('admin.brands.index')->with('success', 'Brand "'.$brand->name.'" created.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.form', compact('brand'));
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('brands', $data['name'], $brand->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['logo']);

        $brand->fill($data);

        if ($request->hasFile('logo')) {
            $old = $brand->logo;
            $brand->logo = $this->images->storeSingle($request->file('logo'), 'brands', 400, $data['slug']);
            $this->images->delete($old);
        }

        $brand->save();

        return redirect()->route('admin.brands.index')->with('success', 'Brand "'.$brand->name.'" updated.');
    }

    public function toggle(Brand $brand): RedirectResponse
    {
        $brand->is_active = ! $brand->is_active;
        $brand->save();

        return back()->with('success', $brand->name.' is now '.($brand->is_active ? 'active' : 'hidden').'.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', 'Move the products off "'.$brand->name.'" before deleting it.');
        }

        $name = $brand->name;

        $this->images->delete($brand->logo);
        $this->catalog->releaseUniqueColumns($brand, ['slug']);
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', 'Brand "'.$name.'" deleted.');
    }
}
