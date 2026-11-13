<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * CSV product import with a per-row validation report.
 *
 * Nothing is written unless the row passes validation; valid rows are still
 * imported when other rows fail, and the caller gets a list of every problem
 * with its line number.
 */
class ProductImportService
{
    /** Columns the importer understands. The first six are required. */
    public const COLUMNS = [
        'name', 'sku', 'category', 'price',
        'brand', 'slug', 'short_description', 'description',
        'material', 'color', 'dimensions', 'weight', 'warranty',
        'hsn_code', 'gst_rate', 'sale_price', 'stock_quantity',
        'status', 'is_featured', 'is_best_seller', 'is_new_arrival', 'custom_order_allowed',
    ];

    public const REQUIRED = ['name', 'sku', 'category', 'price'];

    /** Hard cap so one upload cannot run away with the request. */
    public const MAX_ROWS = 2000;

    public function __construct(protected CatalogService $catalog) {}

    /**
     * @return array{imported: int, updated: int, failed: int, errors: array<int, array{line: int, sku: string, messages: string[]}>, total: int}
     */
    public function import(string $absolutePath, bool $updateExisting = true): array
    {
        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            return $this->emptyResult(['line' => 0, 'sku' => '', 'messages' => ['The uploaded file could not be opened.']]);
        }

        try {
            $header = $this->readHeader($handle);

            if ($header === null) {
                return $this->emptyResult(['line' => 1, 'sku' => '', 'messages' => ['The file is empty or has no header row.']]);
            }

            $missing = array_diff(self::REQUIRED, $header);

            if ($missing) {
                return $this->emptyResult([
                    'line' => 1,
                    'sku' => '',
                    'messages' => ['Missing required column(s): '.implode(', ', $missing).'.'],
                ]);
            }

            return $this->processRows($handle, $header, $updateExisting);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  string[]  $header
     */
    protected function processRows($handle, array $header, bool $updateExisting): array
    {
        $categories = $this->lookup(Category::query()->get(['id', 'name', 'slug']));
        $brands = $this->lookup(Brand::query()->get(['id', 'name', 'slug']));

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        $total = 0;
        $seenSkus = [];

        $line = 1; // header consumed

        while (($raw = fgetcsv($handle, 0, ',')) !== false) {
            $line++;

            // Skip entirely blank lines.
            if ($raw === [null] || count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $total++;

            if ($total > self::MAX_ROWS) {
                $errors[] = [
                    'line' => $line,
                    'sku' => '',
                    'messages' => ['Import stopped: the file has more than '.number_format(self::MAX_ROWS).' rows.'],
                ];
                $failed++;
                break;
            }

            $row = $this->mapRow($header, $raw);
            $sku = trim((string) ($row['sku'] ?? ''));

            // Duplicate SKU inside the same file.
            if ($sku !== '' && isset($seenSkus[Str::lower($sku)])) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'messages' => ['This SKU appears more than once in the file (line '.$seenSkus[Str::lower($sku)].').']];
                $failed++;
                continue;
            }

            $messages = $this->validateRow($row, $categories, $brands, $updateExisting);

            if ($messages) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'messages' => $messages];
                $failed++;
                continue;
            }

            if ($sku !== '') {
                $seenSkus[Str::lower($sku)] = $line;
            }

            try {
                $wasUpdate = $this->saveRow($row, $categories, $brands);
                $wasUpdate ? $updated++ : $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'messages' => ['Could not be saved: '.$e->getMessage()]];
                $failed++;
            }
        }

        return compact('imported', 'updated', 'failed', 'errors', 'total');
    }

    /**
     * @return string[] validation messages, empty when the row is good
     */
    protected function validateRow(array $row, array $categories, array $brands, bool $updateExisting): array
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        $existing = $sku !== '' ? Product::withTrashed()->where('sku', $sku)->first() : null;

        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_\/]+$/'],
            'category' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'gst_rate' => ['nullable', 'numeric', 'between:0,28'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'hsn_code' => ['nullable', 'regex:/^[0-9]{4,8}$/'],
            'status' => ['nullable', 'in:draft,active,inactive'],
            'short_description' => ['nullable', 'string', 'max:400'],
        ], [
            'sku.regex' => 'The SKU may only contain letters, numbers, hyphens, underscores and slashes.',
            'sale_price.lt' => 'The sale price must be lower than the price.',
            'hsn_code.regex' => 'The HSN code must be 4 to 8 digits.',
        ]);

        $messages = $validator->fails() ? collect($validator->errors()->all())->all() : [];

        // Category must already exist — the importer never invents categories.
        $categoryKey = Str::lower(trim((string) ($row['category'] ?? '')));
        if ($categoryKey !== '' && ! isset($categories[$categoryKey])) {
            $messages[] = 'Category "'.$row['category'].'" was not found. Create it first.';
        }

        $brandKey = Str::lower(trim((string) ($row['brand'] ?? '')));
        if ($brandKey !== '' && ! isset($brands[$brandKey])) {
            $messages[] = 'Brand "'.$row['brand'].'" was not found.';
        }

        if ($existing && ! $updateExisting) {
            $messages[] = 'SKU already exists. Tick "update existing products" to overwrite it.';
        }

        if ($existing && $existing->trashed()) {
            $messages[] = 'SKU belongs to a deleted product. Restore it or use a different SKU.';
        }

        return $messages;
    }

    /**
     * @return bool true when an existing product was updated
     */
    protected function saveRow(array $row, array $categories, array $brands): bool
    {
        $sku = trim((string) $row['sku']);
        $product = Product::where('sku', $sku)->first();
        $isUpdate = $product !== null;

        $attributes = [
            'name' => trim((string) $row['name']),
            'category_id' => $categories[Str::lower(trim((string) $row['category']))],
            'brand_id' => filled($row['brand'] ?? null) ? $brands[Str::lower(trim((string) $row['brand']))] : null,
            'short_description' => $this->nullable($row['short_description'] ?? null),
            'description' => $this->nullable($row['description'] ?? null),
            'material' => $this->nullable($row['material'] ?? null),
            'color' => $this->nullable($row['color'] ?? null),
            'dimensions' => $this->nullable($row['dimensions'] ?? null),
            'weight' => $this->numeric($row['weight'] ?? null),
            'warranty' => $this->nullable($row['warranty'] ?? null),
            'hsn_code' => $this->nullable($row['hsn_code'] ?? null),
            'gst_rate' => $this->numeric($row['gst_rate'] ?? null) ?? (float) settings('gst_default_rate', 18),
            'price' => (float) $row['price'],
            'sale_price' => $this->numeric($row['sale_price'] ?? null),
            'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
            'status' => $this->nullable($row['status'] ?? null) ?? 'draft',
            'custom_order_allowed' => $this->boolean($row['custom_order_allowed'] ?? null),
            'is_featured' => $this->boolean($row['is_featured'] ?? null),
            'is_best_seller' => $this->boolean($row['is_best_seller'] ?? null),
            'is_new_arrival' => $this->boolean($row['is_new_arrival'] ?? null),
        ];

        return DB::transaction(function () use ($product, $attributes, $row, $sku, $isUpdate) {
            if ($product) {
                $product->fill($attributes)->save();

                return true;
            }

            $slug = $this->nullable($row['slug'] ?? null);

            $product = new Product($attributes);
            $product->sku = $sku;
            $product->slug = $slug && ! Product::withTrashed()->where('slug', $slug)->exists()
                ? $slug
                : $this->catalog->uniqueSlug('products', $attributes['name']);
            $product->save();

            return $isUpdate;
        });
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * @param  resource  $handle
     * @return string[]|null
     */
    protected function readHeader($handle): ?array
    {
        $header = fgetcsv($handle, 0, ',');

        if ($header === false || $header === [null]) {
            return null;
        }

        // Strip a UTF-8 BOM that Excel likes to add to the first cell.
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        }

        return array_map(
            fn ($column) => Str::snake(Str::lower(trim((string) $column))),
            $header
        );
    }

    /**
     * @param  string[]  $header
     * @param  array<int, string|null>  $raw
     * @return array<string, string|null>
     */
    protected function mapRow(array $header, array $raw): array
    {
        $row = [];

        foreach ($header as $index => $column) {
            $value = $raw[$index] ?? null;
            $row[$column] = $value === null ? null : trim((string) $value);
        }

        return $row;
    }

    /**
     * Name and slug both resolve to an id, case-insensitively.
     *
     * @return array<string, int>
     */
    protected function lookup($models): array
    {
        $map = [];

        foreach ($models as $model) {
            $map[Str::lower($model->name)] = $model->id;
            $map[Str::lower($model->slug)] = $model->id;
        }

        return $map;
    }

    protected function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }

    protected function numeric(?string $value): ?float
    {
        $value = $this->nullable($value);

        return $value === null ? null : (float) $value;
    }

    protected function boolean(?string $value): bool
    {
        $value = Str::lower((string) $this->nullable($value));

        return in_array($value, ['1', 'yes', 'y', 'true', 'active'], true);
    }

    protected function emptyResult(array $error): array
    {
        return ['imported' => 0, 'updated' => 0, 'failed' => 1, 'errors' => [$error], 'total' => 0];
    }
}
