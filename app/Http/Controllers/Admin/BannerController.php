<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BannerController extends Controller implements HasMiddleware
{
    public function __construct(protected ImageService $images) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:banners.view', only: ['index']),
            new Middleware('permission:banners.create', only: ['create', 'store']),
            new Middleware('permission:banners.edit', only: ['edit', 'update', 'toggle', 'reorder']),
            new Middleware('permission:banners.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $banners = Banner::query()
            ->when($request->filled('placement'), fn (Builder $q) => $q->where('placement', $request->input('placement')))
            ->when($request->input('flag') === 'live', fn (Builder $q) => $q->live())
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.banners.index', [
            'banners' => $banners,
            'placements' => Banner::PLACEMENTS,
            'liveCount' => Banner::live()->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.banners.form', [
            'banner' => new Banner(['placement' => 'hero', 'is_active' => true, 'sort_order' => 0]),
            'placements' => Banner::PLACEMENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $banner = Banner::create($data + ['is_active' => $request->boolean('is_active')]);

        $this->storeImages($request, $banner);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner "'.$banner->title.'" added.');
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.form', [
            'banner' => $banner,
            'placements' => Banner::PLACEMENTS,
        ]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $banner->update($this->validated($request, $banner) + ['is_active' => $request->boolean('is_active')]);

        $this->storeImages($request, $banner);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated.');
    }

    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->is_active = ! $banner->is_active;
        $banner->save();

        return back()->with('success',
            '"'.$banner->title.'" is now '.($banner->is_active ? 'on' : 'off').'.');
    }

    /**
     * Move one banner up or down within its placement.
     *
     * Two numbered fields that have to agree is how a slider ends up with
     * three banners all at position 2, so the order is nudged rather than
     * typed.
     */
    public function reorder(Request $request, Banner $banner): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $neighbour = Banner::where('placement', $banner->placement)
            ->when($direction === 'up',
                fn (Builder $q) => $q->where('sort_order', '<=', $banner->sort_order)->where('id', '!=', $banner->id)
                    ->orderByDesc('sort_order')->orderByDesc('id'),
                fn (Builder $q) => $q->where('sort_order', '>=', $banner->sort_order)->where('id', '!=', $banner->id)
                    ->orderBy('sort_order')->orderBy('id'),
            )
            ->first();

        if (! $neighbour) {
            return back()->with('error', 'That is already as far as it goes.');
        }

        $mine = $banner->sort_order;
        $banner->update(['sort_order' => $neighbour->sort_order]);
        $neighbour->update(['sort_order' => $mine]);

        // Equal numbers would leave the pair in whatever order the database
        // felt like, so nudge them apart.
        if ($mine === $neighbour->sort_order) {
            $banner->update(['sort_order' => $direction === 'up' ? max(0, $mine - 1) : $mine + 1]);
        }

        return back()->with('success', 'Order changed.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->images->delete($banner->image_path, $banner->mobile_image_path);

        $title = $banner->title;
        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner "'.$title.'" deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Banner $banner = null): array
    {
        return $request->validate([
            'placement' => ['required', Rule::in(array_keys(Banner::PLACEMENTS))],
            'title' => ['required', 'string', 'max:160'],
            'highlight' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            // Our own site only: an open redirect on a shop is a phishing
            // link wearing the shop's name.
            'cta_url' => ['nullable', 'string', 'max:500', 'regex:#^/[^/\\\\]#'],
            'image_alt' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'image' => [$banner?->image_path ? 'nullable' : 'required', 'image',
                'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
            'mobile_image' => ['nullable', 'image',
                'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ], [
            'cta_url.regex' => 'The link has to be a path on this site, such as /products or /categories/sofas.',
            'ends_at.after' => 'The end date has to be after the start date.',
            'image.required' => 'A banner needs an image.',
        ]);
    }

    protected function storeImages(Request $request, Banner $banner): void
    {
        if ($request->hasFile('image')) {
            $this->images->delete($banner->image_path);
            $banner->image_path = $this->images->storeSingle($request->file('image'), 'banners', maxEdge: 1920);
        }

        if ($request->hasFile('mobile_image')) {
            $this->images->delete($banner->mobile_image_path);
            $banner->mobile_image_path = $this->images->storeSingle($request->file('mobile_image'), 'banners', maxEdge: 1080);
        }

        $banner->save();
    }
}
