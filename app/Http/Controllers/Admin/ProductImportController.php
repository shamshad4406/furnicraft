<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller implements HasMiddleware
{
    public function __construct(protected ProductImportService $importer) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.view', only: ['index', 'export', 'template']),
            new Middleware('permission:products.create', only: ['store']),
        ];
    }

    public function index(): View
    {
        return view('admin.products.import', [
            'columns' => ProductImportService::COLUMNS,
            'required' => ProductImportService::REQUIRED,
            'maxRows' => ProductImportService::MAX_ROWS,
            'report' => session('import_report'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'update_existing' => ['nullable', 'boolean'],
        ], [
            'file.mimes' => 'Please upload a .csv file. Save an Excel sheet as "CSV UTF-8" first.',
        ]);

        $report = $this->importer->import(
            $request->file('file')->getRealPath(),
            $request->boolean('update_existing'),
        );

        activity('catalog')
            ->causedBy(auth('admin')->user())
            ->withProperties([
                'imported' => $report['imported'],
                'updated' => $report['updated'],
                'failed' => $report['failed'],
                'file' => $request->file('file')->getClientOriginalName(),
            ])
            ->log('Imported products from CSV');

        $message = sprintf(
            '%d product(s) created, %d updated, %d row(s) rejected.',
            $report['imported'],
            $report['updated'],
            $report['failed'],
        );

        return redirect()->route('admin.products.import.index')
            ->with($report['failed'] > 0 ? 'info' : 'success', $message)
            ->with('import_report', $report);
    }

    /**
     * Download the whole catalogue as CSV (opens straight in Excel).
     */
    public function export(): StreamedResponse
    {
        $filename = 'furnicraft-products-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM so Excel reads UTF-8 (₹, accented names) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ProductImportService::COLUMNS);

            Product::query()
                ->with(['category:id,name', 'brand:id,name'])
                ->orderBy('id')
                ->chunk(200, function ($products) use ($out) {
                    foreach ($products as $product) {
                        fputcsv($out, [
                            $product->name,
                            $product->sku,
                            $product->category?->name,
                            $product->price,
                            $product->brand?->name,
                            $product->slug,
                            $product->short_description,
                            $product->description,
                            $product->material,
                            $product->color,
                            $product->dimensions,
                            $product->weight,
                            $product->warranty,
                            $product->hsn_code,
                            $product->gst_rate,
                            $product->sale_price,
                            $product->stock_quantity,
                            $product->status,
                            $product->is_featured ? 'yes' : 'no',
                            $product->is_best_seller ? 'yes' : 'no',
                            $product->is_new_arrival ? 'yes' : 'no',
                            $product->custom_order_allowed ? 'yes' : 'no',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A blank template with one example row.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ProductImportService::COLUMNS);
            fputcsv($out, [
                'Teak Wood Coffee Table', 'TAB-10001', 'Table', '8499',
                '', 'teak-wood-coffee-table', 'Solid teak coffee table', 'Full description here.',
                'Teak Wood', 'Walnut Brown', '110 x 60 x 45 cm', '18.5', '2 Years',
                '94036000', '18', '7499', '12',
                'active', 'yes', 'no', 'yes', 'no',
            ]);

            fclose($out);
        }, 'furnicraft-product-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
