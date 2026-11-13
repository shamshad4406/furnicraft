<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmsPageController extends Controller implements HasMiddleware
{
    public function __construct(protected CatalogService $catalog) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:cms.view', only: ['index']),
            new Middleware('permission:cms.create', only: ['create', 'store']),
            new Middleware('permission:cms.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:cms.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $pages = CmsPage::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.cms.index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin.cms.form', ['page' => new CmsPage(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('cms_pages', $data['title']);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_footer'] = $request->boolean('show_in_footer');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $page = CmsPage::create($data);
        $this->flushFrontCache();

        return redirect()->route('admin.cms.index')->with('success', 'Page "'.$page->title.'" created.');
    }

    public function edit(CmsPage $cm): View
    {
        return view('admin.cms.form', ['page' => $cm]);
    }

    public function update(Request $request, CmsPage $cm): RedirectResponse
    {
        $data = $this->validated($request, $cm);

        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('cms_pages', $data['title'], $cm->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_footer'] = $request->boolean('show_in_footer');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // A seeded page is linked from the storefront, so its slug is fixed.
        if ($cm->is_system) {
            unset($data['slug']);
        }

        $cm->update($data);
        $this->flushFrontCache();

        return redirect()->route('admin.cms.index')->with('success', 'Page "'.$cm->title.'" updated.');
    }

    public function toggle(CmsPage $cm): RedirectResponse
    {
        $cm->is_active = ! $cm->is_active;
        $cm->save();
        $this->flushFrontCache();

        return back()->with('success', $cm->title.' is now '.($cm->is_active ? 'published' : 'hidden').'.');
    }

    public function destroy(CmsPage $cm): RedirectResponse
    {
        if ($cm->is_system) {
            return back()->with('error', 'This page is linked from the website and cannot be deleted. Hide it instead.');
        }

        $title = $cm->title;
        $this->catalog->releaseUniqueColumns($cm, ['slug']);
        $cm->delete();
        $this->flushFrontCache();

        return redirect()->route('admin.cms.index')->with('success', 'Page "'.$title.'" deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('cms_pages', 'slug')->ignore($page?->id)],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'content' => ['nullable', 'string', 'max:60000'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:400'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'show_in_footer' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'slug.unique' => 'Another page already uses this slug.',
        ]);
    }

    protected function flushFrontCache(): void
    {
        cache()->forget('front.footer.pages');
    }
}
