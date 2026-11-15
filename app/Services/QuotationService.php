<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Building, pricing and settling quotations.
 *
 * Prices here are GST-inclusive, exactly as in the catalogue and on orders,
 * so the figure a customer is quoted is the figure they pay. Quoting
 * exclusive of tax and adding it at checkout is how a customer ends up
 * arguing about the total.
 */
class QuotationService
{
    public function __construct(
        protected OrderNumberService $numbers,
        protected InvoiceService $invoices,
        protected OrderWorkflow $workflow,
    ) {}

    /**
     * Start a quotation, optionally against an enquiry.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?Enquiry $enquiry = null, ?User $actor = null): Quotation
    {
        $validity = (int) settings('quotation_validity_days', 15);

        return DB::transaction(function () use ($data, $enquiry, $actor, $validity) {
            $quotation = Quotation::create($data + [
                'number' => $this->numbers->next('quotation'),
                'enquiry_id' => $enquiry?->id,
                'customer_id' => $enquiry?->customer_id,
                'status' => 'draft',
                'valid_until' => now()->addDays(max(1, $validity))->toDateString(),
                'terms' => settings('quotation_terms'),
                'created_by' => $actor?->id,
            ]);

            if ($enquiry && $enquiry->status === 'new') {
                $enquiry->update(['status' => 'in_progress']);
            }

            return $quotation;
        });
    }

    /**
     * Replace the lines and re-price.
     *
     * Wholesale replacement rather than a diff: a quotation is a single
     * offer, and half-updating one is how a customer ends up holding a
     * document whose lines and total disagree.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function setItems(Quotation $quotation, array $lines): Quotation
    {
        return DB::transaction(function () use ($quotation, $lines) {
            $quotation->items()->delete();

            foreach (array_values($lines) as $index => $line) {
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
                $gstRate = (float) ($line['gst_rate'] ?? settings('gst_default_rate', 18));

                $subtotal = round($unitPrice * $quantity, 2);

                $quotation->items()->create([
                    'product_id' => $line['product_id'] ?? null,
                    'product_variant_id' => $line['product_variant_id'] ?? null,
                    'name' => $line['name'],
                    'description' => $line['description'] ?? null,
                    'sku' => $line['sku'] ?? null,
                    'hsn_code' => $line['hsn_code'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $subtotal,
                    'gst_rate' => $gstRate,
                    // Prices include GST, so the tax is taken back out of
                    // the line rather than added on top.
                    'tax_amount' => $this->taxWithin($subtotal, $gstRate),
                    'line_total' => $subtotal,
                    'sort_order' => $index,
                ]);
            }

            return $this->reprice($quotation->fresh('items'));
        });
    }

    /**
     * Recalculate the totals and the place of supply from the current lines.
     */
    public function reprice(Quotation $quotation): Quotation
    {
        $items = $quotation->items;

        $subtotal = round((float) $items->sum('line_subtotal'), 2);
        $tax = round((float) $items->sum('tax_amount'), 2);

        $discount = min(round((float) $quotation->discount, 2), $subtotal);
        $delivery = round((float) $quotation->delivery_charge, 2);

        $state = (string) ($quotation->shipping_address['state'] ?? '');

        $quotation->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_charge' => $delivery,
            'tax_total' => $tax,
            'grand_total' => max(0, round($subtotal - $discount + $delivery, 2)),
            'is_interstate' => $this->invoices->isInterstate((string) settings('gst_state', ''), $state),
            'place_of_supply' => $state ?: null,
            'place_of_supply_code' => $this->invoices->stateCode($state),
        ]);

        return $quotation->fresh('items');
    }

    /**
     * The GST contained in a tax-inclusive amount.
     */
    public function taxWithin(float $inclusiveAmount, float $rate): float
    {
        if ($rate <= 0) {
            return 0.0;
        }

        return round($inclusiveAmount - ($inclusiveAmount / (1 + $rate / 100)), 2);
    }

    /**
     * One row per GST rate, the way a tax document has to summarise it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function taxSummary(Quotation $quotation): Collection
    {
        $interstate = (bool) $quotation->is_interstate;

        return $quotation->items
            ->groupBy(fn ($item) => (string) (float) $item->gst_rate)
            ->map(function (Collection $items, string $rate) use ($interstate) {
                $tax = round((float) $items->sum('tax_amount'), 2);
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
     * Hand it to the customer.
     *
     * @throws QuotationNotReady
     */
    public function send(Quotation $quotation): Quotation
    {
        if ($quotation->items()->count() === 0) {
            throw new QuotationNotReady('Add at least one line before sending this quotation.');
        }

        if ((float) $quotation->grand_total <= 0) {
            throw new QuotationNotReady('A quotation has to come to more than zero before it goes out.');
        }

        if (in_array($quotation->status, Quotation::FINAL, true)) {
            throw new QuotationNotReady('This quotation has been settled and cannot be sent again.');
        }

        $quotation->update([
            'status' => 'sent',
            'sent_at' => $quotation->sent_at ?? now(),
        ]);

        $quotation->enquiry?->update(['status' => 'quoted']);

        return $quotation->fresh();
    }

    public function accept(Quotation $quotation, ?string $note = null): Quotation
    {
        $quotation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'customer_note' => $note ?? $quotation->customer_note,
        ]);

        return $quotation->fresh();
    }

    public function decline(Quotation $quotation, ?string $reason = null): Quotation
    {
        $quotation->update([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);

        return $quotation->fresh();
    }

    /**
     * Turn an accepted quotation into a real order.
     *
     * A copy, not a re-interpretation: the customer agreed to these figures,
     * so the order carries them across unchanged rather than re-pricing
     * against a catalogue that may have moved since.
     *
     * No stock is held. A quotation is usually for something being made, and
     * holding stock for thirty minutes against an order placed by staff
     * would only release it again before anyone acted on it.
     *
     * @throws QuotationNotReady
     */
    public function convertToOrder(Quotation $quotation, ?User $actor = null): Order
    {
        if ($quotation->order_id) {
            return $quotation->order;
        }

        if ($quotation->status !== 'accepted') {
            throw new QuotationNotReady('Only an accepted quotation can become an order.');
        }

        if (blank($quotation->shipping_address)) {
            throw new QuotationNotReady('Add a delivery address to this quotation before converting it.');
        }

        return DB::transaction(function () use ($quotation, $actor) {
            $order = Order::create([
                'order_number' => $this->numbers->next('order'),
                'idempotency_key' => 'quotation-'.$quotation->id,
                'customer_id' => $quotation->customer_id,

                'customer_name' => $quotation->customer_name,
                'customer_email' => $quotation->customer_email,
                'customer_phone' => $quotation->customer_phone,
                'gst_number' => $quotation->gst_number,

                'shipping_address' => $quotation->shipping_address,
                'billing_address' => $quotation->billing_address ?: $quotation->shipping_address,
                'shipping_pincode' => $quotation->shipping_address['pincode'] ?? null,

                'subtotal' => $quotation->subtotal,
                'discount' => $quotation->discount,
                'delivery_charge' => $quotation->delivery_charge,
                'tax_total' => $quotation->tax_total,
                'grand_total' => $quotation->grand_total,
                'amount_paid' => 0,
                'amount_due' => $quotation->grand_total,

                'is_interstate' => $quotation->is_interstate,
                'place_of_supply' => $quotation->place_of_supply,
                'place_of_supply_code' => $quotation->place_of_supply_code,

                // Staff-raised, so it is paid against an invoice rather than
                // through the storefront's payment step.
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'status' => 'pending',

                'delivery_days' => $quotation->delivery_days,
                'notes' => 'Raised from quotation '.$quotation->number.'.',
                'placed_at' => now(),
            ]);

            foreach ($quotation->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku ?: 'CUSTOM',
                    'variant_label' => null,
                    'hsn_code' => $item->hsn_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_subtotal' => $item->line_subtotal,
                    'gst_rate' => $item->gst_rate,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                    'weight' => null,
                ]);
            }

            $this->workflow->recordPlacement($order);

            $quotation->update(['status' => 'converted', 'order_id' => $order->id]);
            $quotation->enquiry?->update(['status' => 'converted']);

            return $order->fresh('items');
        });
    }
}
