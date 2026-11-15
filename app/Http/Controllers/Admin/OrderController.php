<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Shipment;
use App\Models\User;
use App\Services\InvalidStatusTransition;
use App\Services\InvoiceService;
use App\Services\OrderWorkflow;
use App\Services\ReturnService;
use App\Services\ShipmentService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller implements HasMiddleware
{
    public function __construct(
        protected OrderWorkflow $workflow,
        protected InvoiceService $invoices,
        protected ShipmentService $shipments,
        protected ReturnService $returns,
    ) {}

    /**
     * There is no delete. An order is a financial record — it is cancelled,
     * never removed — so `orders.delete` grants nothing here.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['index', 'show', 'invoice', 'export']),
            new Middleware('permission:orders.edit', only: ['updateStatus', 'raiseInvoice', 'updateNotes']),
        ];
    }

    public function index(Request $request): View
    {
        $orders = $this->filtered($request)
            ->with('items:id,order_id,name,quantity')
            ->withCount('items')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'summary' => $this->summary(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'items.product:id,slug',
            'payments',
            'statusHistories.changedBy:id,name',
            'shipments.items.orderItem:id,name',
            'shipments.assignee:id,name',
            'returns.items',
            'customer:id,name,email',
            'cancelledBy:id,name',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'allowedNext' => $this->workflow->allowedNext($order),
            'statuses' => Order::STATUSES,
            'canInvoice' => $this->invoices->canRaise($order),
            'taxSummary' => $this->invoices->taxSummary($order),
            'taxTotals' => $this->invoices->totals($order),

            // For the "send a consignment" and "raise a return" forms, both of
            // which have to know what is left rather than what was ordered.
            'pendingItems' => $this->shipments->pendingItems($order),
            'returnableItems' => $this->returns->returnableItems($order),
            'shipmentStatuses' => Shipment::STATUSES,
            'returnTypes' => OrderReturn::TYPES,
            'returnReasons' => OrderReturn::REASONS,
            'deliveryStaff' => User::where('is_active', true)
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['Delivery', 'Admin']))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Move the order along. The workflow decides whether it may move at all;
     * this only reports the answer.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'note' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ]);

        // An unticked checkbox sends nothing at all, so the default here has
        // to be false — defaulting to true would email the customer however
        // deliberately staff had unticked the box.
        $notify = $request->boolean('notify');

        try {
            $history = $this->workflow->moveTo(
                $order,
                $data['status'],
                $data['note'] ?? null,
                auth('admin')->user(),
                $notify,
            );
        } catch (InvalidStatusTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Order moved to '.mb_strtolower(Order::STATUSES[$data['status']]).'.';

        if ($history->customer_notified) {
            $message .= $this->notifyCustomer($order->fresh(), $data['status'], $data['note'] ?? null)
                ? ' The customer has been emailed.'
                : ' The customer could not be emailed — check the mail settings.';
        }

        return back()->with('success', $message);
    }

    public function raiseInvoice(Order $order): RedirectResponse
    {
        if (! $this->invoices->canRaise($order)) {
            return back()->with('error', $order->hasInvoice()
                ? 'This order already has invoice '.$order->invoice_number.'.'
                : 'An invoice can only be raised once the order is confirmed.');
        }

        $order = $this->invoices->raise($order);

        return back()->with('success', 'Invoice '.$order->invoice_number.' raised.');
    }

    /**
     * The printable tax invoice. Its own layout, no admin chrome, sized for
     * A4 — the browser's "save as PDF" is the download button.
     */
    public function invoice(Order $order): Renderable
    {
        abort_unless($order->hasInvoice(), 404, 'No invoice has been raised for this order yet.');

        $order->load('items');

        return view('admin.orders.invoice', [
            'order' => $order,
            'taxSummary' => $this->invoices->taxSummary($order),
            'taxTotals' => $this->invoices->totals($order),
        ]);
    }

    /**
     * The filtered list as a CSV, for the accountant and the spreadsheet.
     *
     * Streamed and chunked: a year of orders should not have to fit in
     * memory to be exported. The same filters as the screen, so what you are
     * looking at is what you get.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('items:id,order_id,name,quantity');

        $filename = 'furnicraft-orders-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // The BOM is what makes Excel read the rupee sign and any
            // non-ASCII name correctly instead of as mojibake.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'order_number', 'invoice_number', 'placed_at', 'status', 'payment_status',
                'payment_method', 'customer_name', 'customer_email', 'customer_phone',
                'city', 'state', 'pincode', 'items', 'quantity',
                'subtotal', 'discount', 'delivery', 'tax', 'total', 'paid', 'due',
                'coupon', 'place_of_supply', 'tax_type',
            ]);

            $query->chunk(200, function ($orders) use ($out) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->order_number,
                        $order->invoice_number,
                        ($order->placed_at ?? $order->created_at)->format('Y-m-d H:i'),
                        $order->status,
                        $order->payment_status,
                        $order->payment_method,
                        $order->customer_name,
                        $order->customer_email,
                        $order->customer_phone,
                        $order->shipping_address['city'] ?? '',
                        $order->shipping_address['state'] ?? '',
                        $order->shipping_pincode,
                        $order->items->count(),
                        $order->items->sum('quantity'),
                        $order->subtotal,
                        $order->discount,
                        $order->delivery_charge,
                        $order->tax_total,
                        $order->grand_total,
                        $order->amount_paid,
                        $order->amount_due,
                        $order->coupon_code,
                        $order->place_of_supply,
                        $order->is_interstate ? 'IGST' : 'CGST+SGST',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateNotes(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update(['notes' => $data['notes'] ?? null]);

        return back()->with('success', 'Notes saved.');
    }

    /**
     * Headline counts for the strip above the list.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) $counts->get('pending', 0),
            'confirmed' => (int) $counts->get('confirmed', 0),
            'processing' => (int) $counts->get('processing', 0),
            'shipped' => (int) $counts->get('shipped', 0),
            'unpaid' => Order::where('payment_status', '!=', 'paid')
                ->whereNotIn('status', ['cancelled', 'returned'])->count(),
            'uninvoiced' => Order::whereNull('invoice_number')
                ->whereNotIn('status', ['pending', 'cancelled'])->count(),
        ];
    }

    /**
     * The list's filters, in one place.
     *
     * The screen and the CSV share this, so an export always matches what
     * the person was looking at when they clicked it.
     *
     * @return Builder<Order>
     */
    protected function filtered(Request $request): Builder
    {
        return Order::query()
            ->when($request->filled('q'), fn (Builder $q) => $this->search($q, (string) $request->input('q')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payment_status'), fn (Builder $q) => $q->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('payment_method'), fn (Builder $q) => $q->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('placed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('placed_at', '<=', $request->date('to')))
            ->when($request->input('flag') === 'unpaid', fn (Builder $q) => $q
                ->where('payment_status', '!=', 'paid')
                ->whereNotIn('status', ['cancelled', 'returned']))
            ->when($request->input('flag') === 'uninvoiced', fn (Builder $q) => $q
                ->whereNull('invoice_number')
                ->whereNotIn('status', ['pending', 'cancelled']))
            ->latest('id');
    }

    protected function search(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(fn (Builder $q) => $q
            ->where('order_number', 'like', $like)
            ->orWhere('invoice_number', 'like', $like)
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('customer_email', 'like', $like)
            ->orWhere('customer_phone', 'like', $like)
            ->orWhere('shipping_pincode', 'like', $like)
        );
    }

    /**
     * A failed email must not undo a status change that really happened, so
     * this reports rather than throws.
     */
    protected function notifyCustomer(Order $order, string $status, ?string $note): bool
    {
        try {
            Mail::to($order->customer_email)->send(new OrderStatusChanged($order, $status, $note));

            return true;
        } catch (\Throwable $e) {
            Log::error('Order status email failed', [
                'order' => $order->order_number,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
