<?php

namespace App\Services;

use App\Exceptions\InvalidImageException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Converts uploads to WebP and writes a thumbnail alongside, using GD.
 *
 * Everything lands on the "public" disk. Returns the stored paths plus the
 * final dimensions and byte size so the caller can persist them.
 */
class ImageService
{
    /** Longest edge of the stored image. */
    public const MAX_EDGE = 1600;

    /** Longest edge of the generated thumbnail. */
    public const THUMB_EDGE = 400;

    /** WebP quality, 0-100. */
    public const QUALITY = 82;

    /**
     * Refuse absurd pixel counts before decoding. A small file can still
     * expand to gigabytes in memory ("decompression bomb"), and GD allocates
     * roughly 4 bytes per pixel.
     */
    public const MAX_PIXELS = 50_000_000;

    /** Raster formats only — SVG can carry script and is never decoded here. */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(protected string $disk = 'public') {}

    public static function isSupported(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /**
     * @return array{path: string, thumbnail_path: string, width: int, height: int, size: int, original_name: string}
     */
    public function storeOptimised(UploadedFile $file, string $folder, ?string $basename = null): array
    {
        if (! self::isSupported()) {
            throw new RuntimeException('GD with WebP support is required to process images.');
        }

        $image = $this->readImage($file);

        try {
            $name = Str::slug($basename ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
            $name = Str::limit($name, 60, '').'-'.Str::lower(Str::random(8));

            $full = $this->resizeWithin($image, self::MAX_EDGE);
            $thumb = $this->resizeWithin($image, self::THUMB_EDGE);

            $path = trim($folder, '/')."/{$name}.webp";
            $thumbPath = trim($folder, '/')."/thumbs/{$name}.webp";

            $this->put($full, $path);
            $this->put($thumb, $thumbPath);

            $result = [
                'path' => $path,
                'thumbnail_path' => $thumbPath,
                'width' => imagesx($full),
                'height' => imagesy($full),
                'size' => (int) Storage::disk($this->disk)->size($path),
                'original_name' => $file->getClientOriginalName(),
            ];

            imagedestroy($full);
            imagedestroy($thumb);

            return $result;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Single optimised image with no thumbnail — used for logos and category art.
     */
    public function storeSingle(UploadedFile $file, string $folder, int $maxEdge = 600, ?string $basename = null): string
    {
        if (! self::isSupported()) {
            throw new RuntimeException('GD with WebP support is required to process images.');
        }

        $image = $this->readImage($file);

        try {
            $name = Str::slug($basename ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
            $name = Str::limit($name, 60, '').'-'.Str::lower(Str::random(8));

            $resized = $this->resizeWithin($image, $maxEdge);
            $path = trim($folder, '/')."/{$name}.webp";

            $this->put($resized, $path);
            imagedestroy($resized);

            return $path;
        } finally {
            imagedestroy($image);
        }
    }

    public function delete(?string ...$paths): void
    {
        foreach (array_filter($paths) as $path) {
            if (Storage::disk($this->disk)->exists($path)) {
                Storage::disk($this->disk)->delete($path);
            }
        }
    }

    /* ------------------------------------------------------------ internals */

    /**
     * @return \GdImage
     */
    protected function readImage(UploadedFile $file)
    {
        $path = $file->getRealPath();

        // Inspect the real bytes, not the filename or the browser-supplied type.
        $info = $path ? @getimagesize($path) : false;

        if ($info === false) {
            throw new InvalidImageException('The file is not a readable image.');
        }

        [$width, $height] = $info;
        $mime = $info['mime'] ?? '';

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidImageException('Only JPG, PNG and WebP images are accepted.');
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw new InvalidImageException('That image is too large to process. Please upload one under '
                .number_format(self::MAX_PIXELS / 1_000_000).' megapixels.');
        }

        $contents = file_get_contents($path);
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if ($image === false) {
            throw new InvalidImageException('The uploaded file could not be read as an image.');
        }

        return $this->applyExifOrientation($image, $file);
    }

    /**
     * Photos from phones carry their rotation in EXIF rather than the pixels.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    protected function applyExifOrientation($image, UploadedFile $file)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? null;

        $rotation = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($rotation === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $rotation, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Scale so the longest edge is at most $maxEdge. Never upscales.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    protected function resizeWithin($image, int $maxEdge)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        $ratio = $longest > $maxEdge ? $maxEdge / $longest : 1.0;

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Keep transparency for PNG/WebP sources.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /**
     * @param  \GdImage  $image
     */
    protected function put($image, string $path): void
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);
        $binary = ob_get_clean();

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Failed to encode the image as WebP.');
        }

        Storage::disk($this->disk)->put($path, $binary);
    }
}
