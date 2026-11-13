<?php

use App\Services\SettingService;

if (! function_exists('settings')) {
    /**
     * Resolve the setting service, or read a single setting value.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        /** @var SettingService $service */
        $service = app(SettingService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('setting_file')) {
    function setting_file(string $key): ?string
    {
        return app(SettingService::class)->fileUrl($key);
    }
}

if (! function_exists('asset_v')) {
    /**
     * Asset URL with a cache-busting stamp taken from the file's mtime.
     */
    function asset_v(string $path): string
    {
        $full = public_path($path);
        $version = is_file($full) ? filemtime($full) : null;

        return asset($path).($version ? '?v='.$version : '');
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount using the configured currency symbol.
     */
    function money(float|int|string|null $amount, int $decimals = 0): string
    {
        $symbol = (string) settings('currency_symbol', '₹');

        return $symbol.' '.number_format((float) $amount, $decimals);
    }
}
