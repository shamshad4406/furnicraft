<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customers reviewing what they bought.
 *
 * Open to people without an account, because plenty of buyers check out as
 * guests and their opinion is worth as much. Nothing goes live until a human
 * has read it.
 */
class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviews) {}

    public function create(string $slug): View
    {
        $product = Product::where('slug', $slug)->active()->firstOrFail();

        $customer = auth('customer')->user();

        return view('front.review', [
            'product' => $product,
            'customer' => $customer,
            'eligibility' => $this->reviews->eligibility($product, $customer?->email, $customer?->id),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = Product::where('slug', $slug)->active()->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],

            // Not a real field. A bot fills everything in.
            'website' => ['prohibited'],
        ], [
            'rating.required' => 'Please give it a star rating.',
            'body.min' => 'Tell us a little more — a sentence or two helps the next person.',
            'website.prohibited' => 'Something went wrong with that submission. Please try again.',
        ]);

        $customer = auth('customer')->user();

        $eligibility = $this->reviews->eligibility($product, $data['email'], $customer?->id);

        if (! $eligibility['allowed']) {
            return back()->withInput()->with('error', $eligibility['reason']);
        }

        $review = $this->reviews->submit($product, $data + [
            'customer_id' => $customer?->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('product.show', $product->slug)
            ->with('success', $review->is_verified
                ? 'Thank you — your review will appear once we have read it. We can see you bought this, so it will be marked as a verified purchase.'
                : 'Thank you — your review will appear once we have read it.');
    }
}
