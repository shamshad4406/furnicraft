<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\View\View;

/**
 * Content pages managed from Admin → Content → Pages.
 */
class PageController extends Controller
{
    /**
     * Pages whose content lives in the CMS but that still need a form or other
     * behaviour on top (built out further in Phase 6).
     */
    protected array $withForm = ['contact-us'];

    public function show(string $slug): View
    {
        $page = CmsPage::query()->active()->where('slug', $slug)->firstOrFail();

        return view('front.page', [
            'page' => $page,
            'showContactDetails' => in_array($page->slug, $this->withForm, true),
        ]);
    }
}
