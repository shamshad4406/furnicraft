<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raises GST tax invoices against delivered or paid orders.
 *
 * Two things here are not negotiable, because a tax invoice is a legal
 * document rather than a receipt:
 *
 *   1. The number is issued once and never changes. A reprint six months
 *      later must produce the identical document.
 *   2. The CGST/SGST/IGST split is decided by where the goods went, not by
 *      what Settings happens to say today — so the place of supply and the
 *      seller's GSTIN are frozen onto the order when the invoice is raised.
 */
class InvoiceService
{
    /**
     * GST state codes, as used in the first two digits of every GSTIN.
     *
     * The place of supply has to be named with its code on an invoice, and
     * the delivery address only gives us a state name, so this maps one to
     * the other. Names are matched loosely — "Delhi", "NCT of Delhi" and
     * "New Delhi" are all 07.
     *
     * @var array<string, string>
     */
    public const STATE_CODES = [
        'jammu and kashmir' => '01', 'himachal pradesh' => '02', 'punjab' => '03',
        'chandigarh' => '04', 'uttarakhand' => '05', 'haryana' => '06',
        'delhi' => '07', 'rajasthan' => '08', 'uttar pradesh' => '09',
        'bihar' => '10', 'sikkim' => '11', 'arunachal pradesh' => '12',
        'nagaland' => '13', 'manipur' => '14', 'mizoram' => '15',
        'tripura' => '16', 'meghalaya' => '17', 'assam' => '18',
        'west bengal' => '19', 'jharkhand' => '20', 'odisha' => '21',
        'chhattisgarh' => '22', 'madhya pradesh' => '23', 'gujarat' => '24',
        'dadra and nagar haveli and daman and diu' => '26', 'maharashtra' => '27',
        'karnataka' => '29', 'goa' => '30', 'lakshadweep' => '31',
        'kerala' => '32', 'tamil nadu' => '33', 'puducherry' => '34',
        'andaman and nicobar islands' => '35', 'telangana' => '36',
        'andhra pradesh' => '37', 'ladakh' => '38', 'other territory' => '97',
    ];

    /**
     * How people actually type state names.
     *
     * Checkout takes the state as free text, so "UP", "U.P." and "Uttar
     * Pradesh" all arrive. Getting this wrong is not cosmetic: a Delhi shop
     * delivering to UP owes IGST, and falling back to CGST + SGST because the
     * name was not recognised puts the wrong tax on a legal document.
     *
     * @var array<string, string>
     */
    public const STATE_ALIASES = [
        'up' => 'uttar pradesh', 'uk' => 'uttarakhand', 'ua' => 'uttarakhand',
        'mp' => 'madhya pradesh', 'ap' => 'andhra pradesh', 'ar' => 'arunachal pradesh',
        'hp' => 'himachal pradesh', 'tn' => 'tamil nadu', 'ts' => 'telangana',
        'tg' => 'telangana', 'wb' => 'west bengal', 'jk' => 'jammu and kashmir',
        'j and k' => 'jammu and kashmir', 'cg' => 'chhattisgarh', 'ct' => 'chhattisgarh',
        'od' => 'odisha', 'or' => 'odisha', 'orissa' => 'odisha',
        'ka' => 'karnataka', 'kl' => 'kerala', 'mh' => 'maharashtra',
        'gj' => 'gujarat', 'rj' => 'rajasthan', 'pb' => 'punjab',
        'hr' => 'haryana', 'dl' => 'delhi', 'nct' => 'delhi', 'ncr' => 'delhi',
        'br' => 'bihar', 'jh' => 'jharkhand', 'as' => 'assam',
        'ga' => 'goa', 'ml' => 'meghalaya', 'mn' => 'manipur', 'mz' => 'mizoram',
        'nl' => 'nagaland', 'sk' => 'sikkim', 'tr' => 'tripura',
        'py' => 'puducherry', 'pondicherry' => 'puducherry',
        'ch' => 'chandigarh', 'ld' => 'lakshadweep', 'an' => 'andaman and nicobar islands',
        'dn' => 'dadra and nagar haveli and daman and diu',
        'dd' => 'dadra and nagar haveli and daman and diu',
        'bangalore' => 'karnataka', 'bengaluru' => 'karnataka',
    ];

    public function __construct(protected OrderNumberService $numbers) {}

    /**
     * Whether an invoice may be raised for this order yet.
     *
     * Nothing is invoiced before it is confirmed, and a cancelled order is
     * never invoiced at all.
     */
    public function canRaise(Order $order): bool
    {
        return ! $order->hasInvoice()
            && ! in_array($order->status, ['pending', 'cancelled'], true);
    }

    /**
     * Issue the invoice, or return the order untouched if it already has one.
     *
     * Safe to call twice: the second call is a no-op, so a double-click never
     * burns a number from the sequence.
     */
    public function raise(Order $order): Order
    {
        if ($order->hasInvoice()) {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            // Re-read under a lock: two staff members clicking at once must
            // not both get past the check above.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice_number) {
                return $locked;
            }

            $state = (string) ($locked->shipping_address['state'] ?? '');
            $sellerState = (string) settings('gst_state', '');

            $locked->update([
                'invoice_number' => $this->numbers->next('invoice'),
                'invoiced_at' => now(),
                'is_interstate' => $this->isInterstate($sellerState, $state),
                'place_of_supply' => $state ?: null,
                'place_of_supply_code' => $this->stateCode($state),
                'seller_gst_number' => settings('gst_number') ?: null,
            ]);

            return $locked->fresh('items');
        });
    }

    /**
     * The tax table on the invoice: one row per GST rate, because that is how
     * a GST invoice has to summarise it, not one row per product.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function taxSummary(Order $order): Collection
    {
        $interstate = $this->treatAsInterstate($order);

        return $order->items
            ->groupBy(fn ($item) => (string) (float) $item->gst_rate)
            ->map(function (Collection $items, string $rate) use ($interstate) {
                $tax = round((float) $items->sum('tax_amount'), 2);

                // Taxable value is the price with the tax taken back out —
                // catalogue prices are GST-inclusive.
                $taxable = round((float) $items->sum('line_subtotal') - $tax, 2);

                return [
                    'rate' => (float) $rate,
                    'taxable' => $taxable,
                    'cgst' => $interstate ? 0.0 : round($tax / 2, 2),
                    'sgst' => $interstate ? 0.0 : round($tax - round($tax / 2, 2), 2),
                    'igst' => $interstate ? $tax : 0.0,
                    'total' => $tax,
                ];
            })
            ->sortBy('rate')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function totals(Order $order): array
    {
        $summary = $this->taxSummary($order);

        return [
            'interstate' => $this->treatAsInterstate($order),
            'taxable' => round((float) $summary->sum('taxable'), 2),
            'cgst' => round((float) $summary->sum('cgst'), 2),
            'sgst' => round((float) $summary->sum('sgst'), 2),
            'igst' => round((float) $summary->sum('igst'), 2),
            'tax' => round((float) $summary->sum('total'), 2),
        ];
    }

    /**
     * A raised invoice keeps the split it was issued with; anything else is
     * worked out live, so the preview before raising is accurate too.
     */
    protected function treatAsInterstate(Order $order): bool
    {
        if ($order->hasInvoice()) {
            return (bool) $order->is_interstate;
        }

        return $this->isInterstate(
            (string) settings('gst_state', ''),
            (string) ($order->shipping_address['state'] ?? '')
        );
    }

    /**
     * Different states mean IGST. When we cannot place the delivery state at
     * all, treat it as intra-state: CGST/SGST is the safer default for a
     * domestic shop, and an unrecognised state name is a data problem worth
     * showing on the invoice rather than silently switching the tax type.
     */
    public function isInterstate(string $sellerState, string $deliveryState): bool
    {
        $seller = $this->stateCode($sellerState);
        $buyer = $this->stateCode($deliveryState);

        if ($seller === null || $buyer === null) {
            return false;
        }

        return $seller !== $buyer;
    }

    public function stateCode(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $key = $this->normalise($state);

        if (isset(self::STATE_CODES[$key])) {
            return self::STATE_CODES[$key];
        }

        // "UP", "U.P.", "up " — an abbreviation is a whole answer, not a
        // fragment, so it is resolved before any substring matching.
        if (isset(self::STATE_ALIASES[$key])) {
            return self::STATE_CODES[self::STATE_ALIASES[$key]] ?? null;
        }

        // "New Delhi", "NCT of Delhi", "Delhi NCR" all mean Delhi. Match on
        // the longest state name contained in what we were given, so
        // "Andhra Pradesh" never matches before itself.
        $matches = collect(self::STATE_CODES)
            ->filter(fn (string $code, string $name) => str_contains($key, $name))
            ->sortByDesc(fn (string $code, string $name) => strlen($name));

        return $matches->first();
    }

    /**
     * "J&K", "J & K" and "Jammu and Kashmir" have to come out the same, so
     * the ampersand becomes a spaced word before whitespace is collapsed.
     */
    protected function normalise(string $state): string
    {
        $state = str_replace(['&', '.', ','], [' and ', '', ' '], mb_strtolower($state));

        return trim(preg_replace('/\s+/', ' ', $state));
    }
}
