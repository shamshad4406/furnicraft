<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:180'],
            // The unique indexes cover soft-deleted rows, so these rules do too.
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($id)],
            'sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_\/]+$/', Rule::unique('products', 'sku')->ignore($id)],

            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],

            'short_description' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:20000'],

            'material' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:60'],
            'dimensions' => ['nullable', 'string', 'max:120'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'warranty' => ['nullable', 'string', 'max:120'],

            'hsn_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{4,8}$/'],
            'gst_rate' => ['required', 'numeric', 'between:0,28'],

            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:400'],

            'custom_order_allowed' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_best_seller' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],

            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],

            /* ------------------------------------------------ variants */
            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required_with:variants.*.size,variants.*.color', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_\/]+$/'],
            'variants.*.size' => ['nullable', 'string', 'max:60'],
            'variants.*.color' => ['nullable', 'string', 'max:60'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'variants.*.is_active' => ['nullable', 'boolean'],

            /* ------------------------------------------ specifications */
            'specifications' => ['nullable', 'array', 'max:50'],
            'specifications.*.name' => ['nullable', 'string', 'max:120'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],

            /* ------------------------------------------------- images */
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already used by another product.',
            'sku.regex' => 'The SKU may only contain letters, numbers, hyphens, underscores and slashes.',
            'slug.unique' => 'This slug is already used by another product.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'sale_price.lt' => 'The sale price must be lower than the regular price.',
            'hsn_code.regex' => 'The HSN code must be 4 to 8 digits.',
            'images.*.max' => 'Each image must be 8 MB or smaller.',
        ];
    }

    /**
     * Cross-row checks the rule set cannot express on its own.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateVariants($validator);
        });
    }

    protected function validateVariants($validator): void
    {
        $variants = collect($this->input('variants', []))
            ->filter(fn ($row) => filled($row['sku'] ?? null) || filled($row['size'] ?? null) || filled($row['color'] ?? null));

        $seenSkus = [];
        $seenCombos = [];

        foreach ($variants as $index => $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            // Duplicate inside the submitted form.
            $key = mb_strtolower($sku);
            if (isset($seenSkus[$key])) {
                $validator->errors()->add("variants.{$index}.sku", 'This SKU is repeated in the variant list.');
            }
            $seenSkus[$key] = true;

            // Duplicate against the rest of the catalog.
            $exists = ProductVariant::query()
                ->where('sku', $sku)
                ->when(! empty($row['id']), fn ($q) => $q->where('id', '!=', $row['id']))
                ->exists();

            if ($exists) {
                $validator->errors()->add("variants.{$index}.sku", 'This variant SKU is already used.');
            }

            // Same size + colour twice on one product is meaningless.
            $combo = mb_strtolower(trim((string) ($row['size'] ?? '')).'|'.trim((string) ($row['color'] ?? '')));
            if ($combo !== '|') {
                if (isset($seenCombos[$combo])) {
                    $validator->errors()->add("variants.{$index}.size", 'This size and colour combination is repeated.');
                }
                $seenCombos[$combo] = true;
            }

            // A variant's sale price must undercut its own effective price.
            $price = $row['price'] ?? $this->input('price');
            $salePrice = $row['sale_price'] ?? null;

            if ($salePrice !== null && $salePrice !== '' && $price !== null && (float) $salePrice >= (float) $price) {
                $validator->errors()->add("variants.{$index}.sale_price", 'The variant sale price must be lower than its price.');
            }
        }
    }
}
