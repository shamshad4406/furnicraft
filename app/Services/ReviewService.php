<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer reviews, and the ratings they roll up into.
 *
 * Two things here are worth being careful about.
 *
 * The average is recalculated from approved reviews only and stored on the
 * product, so a listing can sort by rating without a subquery per row — and
 * so a rejected review stops counting the moment it is rejected, rather than
 * when somebody remembers to recalculate.
 *
 * "Verified" is derived from a real delivered order, never ticked by hand.
 * It is the one claim on a review page that carries weight, and a shop that
 * can set it at will has nothing worth saying.
 */
class ReviewService
{
    /**
     * Record a review. It is not published until somebody reads it.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(Product $product, array $data): Review
    {
        $order = $this->purchaseFor($product, $data['email'], $data['customer_id'] ?? null);

        return DB::transaction(function () use ($product, $data, $order) {
            // A second review of the same thing by the same person is an
            // edit of their opinion, not a second opinion.
            $review = Review::withTrashed()->firstOrNew([
                'product_id' => $product->id,
                'email' => $data['email'],
            ]);

            $review->fill([
                'customer_id' => $data['customer_id'] ?? null,
                'order_id' => $order?->id,
                'name' => $data['name'],
                'city' => $data['city'] ?? null,
                'rating' => max(1, min(5, (int) $data['rating'])),
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'is_verified' => $order !== null,
                'ip_address' => $data['ip_address'] ?? null,
                // An edited review goes back into the queue: the old
                // approval was for different words.
                'status' => 'pending',
                'moderated_at' => null,
                'moderated_by' => null,
            ]);

            $review->deleted_at = null;
            $review->save();

            $this->recalculate($product);

            return $review->fresh();
        });
    }

    public function approve(Review $review, ?User $actor = null, ?string $note = null): Review
    {
        $review->update([
            'status' => 'approved',
            'moderation_note' => $note ?? $review->moderation_note,
            'moderated_by' => $actor?->id,
            'moderated_at' => now(),
        ]);

        $this->recalculate($review->product);

        return $review->fresh();
    }

    public function reject(Review $review, ?User $actor = null, string $status = 'rejected', ?string $note = null): Review
    {
        $review->update([
            'status' => $status,
            'is_featured' => false,
            'moderation_note' => $note,
            'moderated_by' => $actor?->id,
            'moderated_at' => now(),
        ]);

        $this->recalculate($review->product);

        return $review->fresh();
    }

    /**
     * The shop answering publicly. Shown under the review.
     */
    public function reply(Review $review, string $body, ?User $actor = null): Review
    {
        $review->update([
            'reply' => $body,
            'replied_at' => now(),
            'moderated_by' => $actor?->id ?? $review->moderated_by,
        ]);

        return $review->fresh();
    }

    /**
     * Pick a review out for the homepage.
     *
     * @throws ReviewNotPublishable
     */
    public function feature(Review $review, bool $featured = true): Review
    {
        if ($featured && ! $review->isPublished()) {
            throw new ReviewNotPublishable('Approve this review before featuring it on the homepage.');
        }

        $review->update(['is_featured' => $featured]);

        return $review->fresh();
    }

    /**
     * Roll the approved reviews up onto the product.
     */
    public function recalculate(Product $product): void
    {
        $stats = Review::query()
            ->where('product_id', $product->id)
            ->approved()
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $count = (int) ($stats->total ?? 0);

        $values = [
            'rating_count' => $count,
            'rating_average' => $count > 0 ? round((float) $stats->average, 2) : 0,
        ];

        // Written by query rather than through the instance. A caller holding
        // a model loaded before the review changed would otherwise find the
        // new figures matching its own stale attributes, nothing dirty, and
        // save() quietly writing nothing — leaving a rating on the product
        // that no published review supports.
        Product::whereKey($product->id)->update($values);

        // Keep the caller's copy in step, without marking it dirty.
        $product->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }

    /**
     * How the ratings are spread, for the bars on a product page.
     *
     * @return Collection<int, array<string, mixed>> five down to one
     */
    public function breakdown(Product $product): Collection
    {
        $counts = Review::where('product_id', $product->id)
            ->approved()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $all = max(1, (int) $counts->sum());

        return collect(range(5, 1))->map(fn (int $star) => [
            'star' => $star,
            'count' => (int) $counts->get($star, 0),
            'percent' => round(((int) $counts->get($star, 0)) / $all * 100, 1),
        ]);
    }

    /**
     * Approved reviews chosen for the homepage, newest first.
     *
     * @return Collection<int, Review>
     */
    public function featured(int $limit = 6): Collection
    {
        return Review::query()
            ->approved()
            ->where('is_featured', true)
            ->with('product:id,name,slug')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Whether this person may review this product.
     *
     * The shop can allow anybody, or insist on a delivered order. Insisting
     * is the honest setting; allowing anybody is what most shops do.
     *
     * @return array{allowed: bool, reason: string|null, order: Order|null}
     */
    public function eligibility(Product $product, ?string $email, ?int $customerId = null): array
    {
        $order = $email ? $this->purchaseFor($product, $email, $customerId) : null;

        if (settings()->bool('reviews_require_purchase', false) && ! $order) {
            return [
                'allowed' => false,
                'reason' => 'Reviews here come from people who have bought the item and had it delivered.',
                'order' => null,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'order' => $order];
    }

    /**
     * A delivered order containing this product, bought by this person.
     *
     * Delivered, not merely placed: somebody who ordered yesterday has not
     * seen the thing yet.
     */
    protected function purchaseFor(Product $product, string $email, ?int $customerId = null): ?Order
    {
        return Order::query()
            ->where('status', 'delivered')
            ->where(function ($query) use ($email, $customerId) {
                $query->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($email)]);

                if ($customerId) {
                    $query->orWhere('customer_id', $customerId);
                }
            })
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->latest('id')
            ->first();
    }
}
