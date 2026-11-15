<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Demo artwork that ships with the application in public/images.
 *
 * It is referenced from the database with a "bundled/" prefix so it is never
 * confused with a staff upload. Keeping it out of storage/app/public means the
 * uploads directory contains only genuine uploads — which lets that directory
 * be locked down hard (no SVG rendering, no script execution) without breaking
 * the demo catalog.
 */
class BundledArt
{
    public const PREFIX = 'bundled/';

    public static function is(?string $path): bool
    {
        return $path !== null && str_starts_with($path, self::PREFIX);
    }

    /**
     * "bundled/categories/sofa.svg" => a URL under /images.
     */
    public static function url(string $path): string
    {
        return asset('images/'.Str::after($path, self::PREFIX));
    }

    /**
     * Reference to store in the database for a file in public/images.
     *
     * Accepts either "categories/sofa.svg" or the public-relative
     * "images/categories/sofa.svg"; both resolve to the same reference.
     */
    public static function reference(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'images/')) {
            $path = Str::after($path, 'images/');
        }

        return self::PREFIX.$path;
    }

    public static function exists(string $path): bool
    {
        return is_file(public_path('images/'.Str::after($path, self::PREFIX)));
    }
}
