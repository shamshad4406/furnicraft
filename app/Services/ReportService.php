<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers the owner actually asks for.
 *
 * One rule runs through every figure here: a cancelled or returned order is
 * not a sale. Reports that quietly include them read well and are wrong, and
 * the person acting on them finds out at the end of the quarter.
 *
 * Revenue means money received — `amount_paid` — not what was invoiced. A
 * cash-on-delivery order that has not been collected yet is not revenue.
 */
class ReportService
{
    /** Orders that never happened as far as the books are concerned. */
    public const EXCLUDED = ['cancelled', 'returned'];

    /**
     * Headline figures for a period.
     *
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $orders = $this->ordersBetween($from, $to);

        $count = (clone $orders)->count();
        $received = (float) (clone $orders)->sum('amount_paid');
        $invoiced = (float) (clone $orders)->sum('grand_total');
        $outstanding = (float) (clone $orders)->sum('amount_due');

        return [
            'orders' => $count,
            'received' => $received,
            'invoiced' => $invoiced,
            'outstanding' => $outstanding,
            'average' => $count > 0 ? round($invoiced / $count, 2) : 0.0,
            'units' => (int) $this->itemsBetween($from, $to)->sum('order_items.quantity'),
            'tax' => (float) (clone $orders)->sum('tax_total'),
            // Counted separately so the loss is visible rather than hidden
            // by its own exclusion from everything else.
            'cancelled' => Order::whereBetween('placed_at', [$from, $to])
                ->where('status', 'cancelled')->count(),
            'returned' => Order::whereBetween('placed_at', [$from, $to])
                ->where('status', 'returned')->count(),
        ];
    }

    /**
     * Sales day by day, for the chart and the table.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function daily(Carbon $from, Carbon $to): Collection
    {
        $rows = $this->ordersBetween($from, $to)
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as orders, SUM(grand_total) as invoiced, SUM(amount_paid) as received')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        // Every day in the range appears, including the quiet ones — a gap
        // in a sales chart reads as missing data rather than no sales.
        $days = collect();
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $days->push([
                'day' => $cursor->copy(),
                'orders' => (int) ($row->orders ?? 0),
                'invoiced' => (float) ($row->invoiced ?? 0),
                'received' => (float) ($row->received ?? 0),
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * What sold, most first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(Carbon $from, Carbon $to, int $limit = 15): Collection
    {
        return $this->itemsBetween($from, $to)
            ->selectRaw('order_items.product_id, order_items.name,
                SUM(order_items.quantity) as units,
                SUM(order_items.line_total) as revenue,
                COUNT(DISTINCT order_items.order_id) as orders')
            ->groupBy('order_items.product_id', 'order_items.name')
            ->orderByDesc('units')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'units' => (int) $row->units,
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ]);
    }

    /**
     * The GST return, one row per rate split into CGST/SGST and IGST.
     *
     * Only invoiced orders count: GST is owed on invoices raised, not on
     * orders someone might still cancel.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function gstSummary(Carbon $from, Carbon $to): Collection
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.invoice_number')
            ->whereBetween('orders.invoiced_at', [$from, $to])
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', self::EXCLUDED)
            ->selectRaw('order_items.gst_rate, orders.is_interstate,
                SUM(order_items.line_subtotal) as gross,
                SUM(order_items.tax_amount) as tax')
            ->groupBy('order_items.gst_rate', 'orders.is_interstate')
            ->orderBy('order_items.gst_rate')
            ->get();

        return $rows->map(function ($row) {
            $tax = round((float) $row->tax, 2);
            $interstate = (bool) $row->is_interstate;

            return [
                'rate' => (float) $row->gst_rate,
                'interstate' => $interstate,
                'taxable' => round((float) $row->gross - $tax, 2),
                'cgst' => $interstate ? 0.0 : round($tax / 2, 2),
                'sgst' => $interstate ? 0.0 : round($tax - round($tax / 2, 2), 2),
                'igst' => $interstate ? $tax : 0.0,
                'total' => $tax,
            ];
        });
    }

    /**
     * What the shelves are worth at cost.
     *
     * @return array<string, mixed>
     */
    public function stockValuation(): array
    {
        $priced = Product::whereNotNull('cost_price')->where('cost_price', '>', 0);

        return [
            'value' => (float) (clone $priced)->selectRaw('SUM(cost_price * stock_quantity) as v')->value('v'),
            'units' => (int) Product::sum('stock_quantity'),
            'priced' => (clone $priced)->count(),
            'unpriced' => Product::where('status', 'active')
                ->where(fn ($q) => $q->whereNull('cost_price')->orWhere('cost_price', '<=', 0))
                ->count(),
        ];
    }

    /**
     * Who buys the most.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topCustomers(Carbon $from, Carbon $to, int $limit = 15): Collection
    {
        return $this->ordersBetween($from, $to)
            ->selectRaw('customer_email, MAX(customer_name) as name,
                COUNT(*) as orders, SUM(amount_paid) as spent')
            ->groupBy('customer_email')
            ->orderByDesc('spent')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'email' => $row->customer_email,
                'name' => $row->name,
                'orders' => (int) $row->orders,
                'spent' => (float) $row->spent,
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    protected function ordersBetween(Carbon $from, Carbon $to)
    {
        return Order::query()
            ->whereBetween('placed_at', [$from, $to])
            ->whereNotIn('status', self::EXCLUDED);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function itemsBetween(Carbon $from, Carbon $to)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', self::EXCLUDED);
    }
}
