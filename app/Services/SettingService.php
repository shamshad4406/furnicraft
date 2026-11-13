<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Central read/write access to the settings table, cached as a single payload.
 */
class SettingService
{
    public const CACHE_KEY = 'furnicraft.settings';

    /**
     * Settings held encrypted at rest. A database dump or a backup should not
     * hand over the mail account.
     *
     * @var string[]
     */
    public const ENCRYPTED = ['mail_password'];

    protected ?array $cached = null;

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $this->cached = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Setting::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                // Settings table may not exist yet (first migrate).
                return [];
            }
        });

        return $this->cached;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value !== null && $value !== '' && in_array($key, self::ENCRYPTED, true)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                // Written before encryption was introduced, or APP_KEY rotated.
                return $default;
            }
        }

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * True when a secret is stored, without revealing it. Lets a form show
     * "saved" instead of echoing the value back into the HTML.
     */
    public function hasSecret(string $key): bool
    {
        $value = $this->all()[$key] ?? null;

        return $value !== null && $value !== '';
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $group);
        }

        $this->flush();
    }

    public function set(string $key, mixed $value, string $group = 'general', string $type = 'text'): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : $value;

        if ($stored !== null && $stored !== '' && in_array($key, self::ENCRYPTED, true)) {
            $stored = Crypt::encryptString((string) $stored);
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'group' => $group, 'type' => $type]
        );

        $this->flush();
    }

    /**
     * Store an uploaded file on the public disk and remember its path.
     */
    public function putFile(string $key, UploadedFile $file, string $folder = 'settings', string $group = 'general'): string
    {
        $old = $this->get($key);

        $path = $file->store($folder, 'public');

        if ($old && $old !== $path && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        $this->set($key, $path, $group, 'file');

        return $path;
    }

    public function fileUrl(string $key): ?string
    {
        $path = $this->get($key);

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    public function flush(): void
    {
        $this->cached = null;
        Cache::forget(self::CACHE_KEY);
    }
}
