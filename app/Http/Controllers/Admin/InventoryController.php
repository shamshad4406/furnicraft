<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockLedger;
use App\Services\StockLedgerRefused;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock on hand, the ledger behind it, and the screen for correcting it.
 *
 * There is no "edit the number" here. Every change is a counted adjustment
 * with a reason, which is the only way the ledger stays worth reading.
 */
class InventoryController extends Controller implements HasMiddleware
{
    public function __construct(protected StockLedger $ledger) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view', only: ['index', 'show', 'movements', 'export']),
            new Middleware('permission:inventory.edit', only: ['adjust', 'updateThreshold']),
        ];
    }

    public function index(Request $request): View
    {
        $products = $this->filtered($request)
            ->with(['category:id,name', 'variants:id,product_id,size,color,stock_quantity'])
            ->paginate(25)
            ->withQueryString();

        return view('admin.inventory.index', [
            'products' => $products,
            'summary' => $this->summary(),
            'types' => StockLedger::TYPES,
        ]);
    }

    /**
     * One product's ledger: how the count got to where it is.
     */
    public function show(Product $product): View
    {
        $product->load('variants:id,product_id,size,color,stock_quantity');

        return view('admin.inventory.show', [
            'product' => $product,
            'movements' => $product->stockMovements()
                ->with('user:id,name')
                ->paginate(30),
            'types' => StockLedger::TYPES,
        ]);
    }

    /**
     * The whole ledger, across every product.
     */
    public function movements(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product:id,name,sku,slug', 'variant:id,size,color', 'user:id,name'])
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->filled('product'), fn (Builder $q) => $q->where('product_id', $request->input('product')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('reference_label', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhereHas('product', fn (Builder $p) => $p
                        ->where('name', 'like', $like)->orWhere('sku', 'like', $like)));
            })
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.inventory.movements', [
            'movements' => $movements,
            'types' => StockLedger::TYPES,
        ]);
    }

    /**
     * Record a physical count, or write something off.
     */
    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'mode' => ['required', Rule::in(['count', 'change'])],
            'counted_quantity' => ['required_if:mode,count', 'nullable', 'integer', 'min:0', 'max:999999'],
            'quantity' => ['required_if:mode,change', 'nullable', 'integer', 'min:-999999', 'max:999999'],
            'type' => ['required', Rule::in(['adjustment', 'damage', 'correction', 'opening'])],
            'reason' => ['required', 'string', 'max:200'],
        ], [
            // A number that changed with no explanation is the whole problem
            // this module exists to solve.
            'reason.required' => 'Say why the count is changing — this is the record somebody reads later.',
            'counted_quantity.required_if' => 'Enter the number you counted.',
            'quantity.required_if' => 'Enter how many to add or take away.',
        ]);

        $variantId = $data['product_variant_id'] ?? null;

        try {
            $movement = $data['mode'] === 'count'
                ? $this->ledger->countTo(
                    $product->id,
                    $variantId,
                    (int) $data['counted_quantity'],
                    $data['reason'],
                    $data['type'],
                )
                : $this->ledger->record(
                    $product->id,
                    $variantId,
                    $data['type'],
                    (int) $data['quantity'],
                    $data['reason'],
                );
        } catch (StockLedgerRefused $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (! $movement) {
            return back()->with('success', 'The count matched what the system had — nothing to record.');
        }

        return back()->with('success',
            'Stock updated: '.$movement->signed_quantity.', now '.$movement->balance_after.'.');
    }

    public function updateThreshold(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $product->update([
            'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
        ]);

        return back()->with('success', 'Reorder level saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('category:id,name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'sku', 'product', 'category', 'in_stock', 'reorder_level',
                'cost_price', 'stock_value', 'selling_price', 'status',
            ]);

            $query->chunk(200, function ($products) use ($out) {
                foreach ($products as $product) {
                    $stock = $product->total_stock;

                    fputcsv($out, [
                        $product->sku,
                        $product->name,
                        $product->category?->name,
                        $stock,
                        $product->effective_low_stock_threshold,
                        $product->cost_price,
                        $product->cost_price ? round((float) $product->cost_price * $stock, 2) : '',
                        $product->effective_price,
                        $product->status,
                    ]);
                }
            });

            fclose($out);
        }, 'furnicraft-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<Product>
     */
    protected function filtered(Request $request): Builder
    {
        return Product::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)->orWhere('sku', 'like', $like));
            })
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category_id', $request->input('category')))
            ->when($request->input('flag') === 'low', fn (Builder $q) => $q->lowStock())
            ->when($request->input('flag') === 'out', fn (Builder $q) => $q->where('stock_quantity', '<=', 0)
                ->whereDoesntHave('variants', fn (Builder $v) => $v->where('stock_quantity', '>', 0)))
            ->when($request->input('flag') === 'uncosted', fn (Builder $q) => $q->whereNull('cost_price'))
            ->when(! $request->filled('flag'), fn (Builder $q) => $q->orderBy('name'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(): array
    {
        $value = Product::query()
            ->whereNotNull('cost_price')
            ->selectRaw('SUM(cost_price * stock_quantity) as total')
            ->value('total');

        return [
            'low' => Product::lowStock()->count(),
            'out' => Product::where('status', 'active')->where('stock_quantity', '<=', 0)
                ->whereDoesntHave('variants', fn (Builder $v) => $v->where('stock_quantity', '>', 0))
                ->count(),
            'uncosted' => Product::whereNull('cost_price')->where('status', 'active')->count(),
            'value' => (float) ($value ?? 0),
        ];
    }
}
