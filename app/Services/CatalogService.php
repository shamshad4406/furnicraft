<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Slug / SKU generation and the tidy-up needed so a soft-deleted row does not
 * keep holding a unique slug or SKU forever.
 */
class CatalogService
{
    /**
     * A slug that is unique across the table, including soft-deleted rows
     * (the unique index covers them too).
     */
    public function uniqueSlug(string $table, string $source, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($this->exists($table, $column, $slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Build an SKU like "SOF-4821" from the product name, retrying until free.
     */
    public function generateSku(string $name, ?int $ignoreId = null): string
    {
        $prefix = Str::upper(Str::substr(Str::slug($name, ''), 0, 3)) ?: 'FUR';

        do {
            $sku = $prefix.'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while ($this->exists('products', 'sku', $sku, $ignoreId));

        return $sku;
    }

    public function uniqueVariantSku(string $base, int $index = 1): string
    {
        $sku = $base;
        $n = $index;

        while ($this->exists('product_variants', 'sku', $sku)) {
            $sku = $base.'-'.$n;
            $n++;
        }

        return $sku;
    }

    /**
     * Free up the unique columns of a model that is about to be soft deleted,
     * so the same slug/SKU can be used again by a new record.
     */
    public function releaseUniqueColumns(Model $model, array $columns = ['slug', 'sku']): void
    {
        $suffix = '-del'.$model->getKey();
        $changes = [];

        foreach ($columns as $column) {
            if (! isset($model->{$column})) {
                continue;
            }

            $value = (string) $model->{$column};

            if (str_ends_with($value, $suffix)) {
                continue;
            }

            $changes[$column] = Str::limit($value, 80, '').$suffix;
        }

        if ($changes) {
            $model->forceFill($changes)->saveQuietly();
        }
    }

    protected function exists(string $table, string $column, string $value, ?int $ignoreId = null): bool
    {
        $query = DB::table($table)->where($column, $value);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
