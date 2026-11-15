<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewNotPublishable;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Moderating what customers say.
 *
 * Nothing is published until somebody has read it. The queue is the default
 * view, because a moderation screen nobody opens is the same as no
 * moderation at all.
 */
class ReviewController extends Controller implements HasMiddleware
{
    public function __construct(protected ReviewService $reviews) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:reviews.view', only: ['index']),
            new Middleware('permission:reviews.edit', only: ['approve', 'reject', 'reply', 'feature']),
            new Middleware('permission:reviews.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        // Waiting ones first by default: that is the work.
        $status = $request->input('status', 'pending');

        $reviews = Review::query()
            ->with(['product:id,name,slug,sku', 'moderator:id,name'])
            ->when($status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('body', 'like', $like)
                    ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', $like)));
            })
            ->when($request->filled('rating'), fn (Builder $q) => $q->where('rating', $request->input('rating')))
            ->when($request->input('flag') === 'verified', fn (Builder $q) => $q->where('is_verified', true))
            ->when($request->input('flag') === 'featured', fn (Builder $q) => $q->where('is_featured', true))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'status' => $status,
            'statuses' => Review::STATUSES,
            'summary' => [
                'pending' => Review::pending()->count(),
                'approved' => Review::approved()->count(),
                'featured' => Review::approved()->where('is_featured', true)->count(),
                'average' => round((float) Review::approved()->avg('rating'), 2),
            ],
        ]);
    }

    public function approve(Request $request, Review $review): RedirectResponse
    {
        $this->reviews->approve($review, auth('admin')->user(), $request->input('moderation_note'));

        return back()->with('success', 'Review published.');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['rejected', 'spam'])],
            'moderation_note' => ['nullable', 'string', 'max:300'],
        ]);

        $this->reviews->reject(
            $review,
            auth('admin')->user(),
            $data['status'],
            $data['moderation_note'] ?? null,
        );

        return back()->with('success', $data['status'] === 'spam'
            ? 'Marked as spam.'
            : 'Review left unpublished.');
    }

    /**
     * The shop answering in public, under the review.
     */
    public function reply(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ], [
            'reply.required' => 'Write the reply before saving it.',
        ]);

        $this->reviews->reply($review, $data['reply'], auth('admin')->user());

        return back()->with('success', 'Reply saved. It appears under the review.');
    }

    public function feature(Request $request, Review $review): RedirectResponse
    {
        try {
            $this->reviews->feature($review, $request->boolean('featured', ! $review->is_featured));
        } catch (ReviewNotPublishable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $review->fresh()->is_featured
            ? 'Shown on the homepage.'
            : 'Removed from the homepage.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $product = $review->product;

        $review->delete();

        // The average has to stop counting it immediately.
        $this->reviews->recalculate($product);

        return back()->with('success', 'Review deleted.');
    }
}
