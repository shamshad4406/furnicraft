<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseNotPossible;
use App\Services\PurchaseService;
use App\Services\StockLedgerRefused;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(protected PurchaseService $purchases) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchases.view', only: ['index', 'show']),
            new Middleware('permission:purchases.create', only: ['create', 'store']),
            new Middleware('permission:purchases.edit', only: ['update', 'updateItems', 'place', 'receive', 'cancel']),
            new Middleware('permission:purchases.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name,code', 'creator:id,name'])
            ->withCount('items')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('number', 'like', $like)
                    ->orWhere('supplier_invoice_number', 'like', $like)
                    ->orWhereHas('supplier', fn (Builder $s) => $s
                        ->where('name', 'like', $like)->orWhere('code', 'like', $like)));
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('supplier'), fn (Builder $q) => $q->where('supplier_id', $request->input('supplier')))
            ->when($request->input('flag') === 'open', fn (Builder $q) => $q->open())
            ->when($request->input('flag') === 'overdue', fn (Builder $q) => $q->overdue())
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchases.index', [
            'orders' => $orders,
            'statuses' => PurchaseOrder::STATUSES,
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'draft' => PurchaseOrder::where('status', 'draft')->count(),
                'ordered' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->count(),
                'overdue' => PurchaseOrder::overdue()->count(),
                'value' => (float) PurchaseOrder::whereIn('status', ['ordered', 'partial'])->sum('grand_total'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.purchases.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name', 'code', 'lead_time_days']),
            'selected' => $request->input('supplier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'expected_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'expected_on.after_or_equal' => 'The expected date cannot be in the past.',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);

        $order = $this->purchases->create($supplier, [
            'expected_on' => $data['expected_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], auth('admin')->user());

        return redirect()->route('admin.purchases.show', $order)
            ->with('success', 'Purchase order '.$order->number.' started. Add the lines and place it.');
    }

    public function show(PurchaseOrder $purchase): View
    {
        $purchase->load(['items.product:id,name,sku', 'supplier', 'creator:id,name']);

        return view('admin.purchases.show', [
            'order' => $purchase,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'gst_rate', 'cost_price']),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchase): RedirectResponse
    {
        if (! $purchase->isEditable()) {
            return back()->with('error', 'Only a draft purchase order can be changed.');
        }

        $data = $request->validate([
            'expected_on' => ['nullable', 'date'],
            'other_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchase->update([
            'expected_on' => $data['expected_on'] ?? null,
            'other_charges' => $data['other_charges'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->purchases->retotal($purchase->fresh('items'));

        return back()->with('success', 'Purchase order updated.');
    }

    public function updateItems(Request $request, PurchaseOrder $purchase): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.gst_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'items.required' => 'A purchase order needs at least one line.',
        ]);

        try {
            $this->purchases->setItems($purchase, $data['items']);
        } catch (PurchaseNotPossible $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Lines saved.');
    }

    public function place(PurchaseOrder $purchase): RedirectResponse
    {
        try {
            $this->purchases->place($purchase);
        } catch (PurchaseNotPossible $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order '.$purchase->number.' placed. Receive against it as it arrives.');
    }

    /**
     * Book a delivery in. This is where stock actually arrives.
     */
    public function receive(Request $request, PurchaseOrder $purchase): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $purchase = $this->purchases->receive(
                $purchase,
                $data['quantities'],
                $data['supplier_invoice_number'] ?? null,
                auth('admin')->user(),
            );
        } catch (PurchaseNotPossible|StockLedgerRefused $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $purchase->status === 'received'
            ? 'All of '.$purchase->number.' has now arrived and is in stock.'
            : 'Delivery booked in. '.$purchase->outstanding_quantity.' unit(s) still to come.');
    }

    public function cancel(Request $request, PurchaseOrder $purchase): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $this->purchases->cancel($purchase, $data['cancel_reason'] ?? null);
        } catch (PurchaseNotPossible $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function destroy(PurchaseOrder $purchase): RedirectResponse
    {
        if ($purchase->status !== 'draft') {
            return back()->with('error',
                'Only a draft can be deleted. An order that went to a supplier is a record — cancel it instead.');
        }

        $number = $purchase->number;
        $purchase->delete();

        return redirect()->route('admin.purchases.index')
            ->with('success', 'Draft '.$number.' deleted.');
    }
}
