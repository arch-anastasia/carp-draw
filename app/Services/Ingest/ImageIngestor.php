<?php

namespace App\Services\Ingest;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageIngestor
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    private const WEBP_QUALITY = 82;

    /**
     * Resolve an image reference to the id of its `media` row.
     *
     * This is the seam every ingestor uses now that image paths are normalized
     * into one table. It resolves the bytes exactly as before and then records
     * the URL - so re-ingesting the same authored file is idempotent and does
     * not accumulate duplicate media rows.
     *
     * Alt text comes in beside the image because the authored files keep their
     * readable shape ('image' plus 'image_alt'); the schema is what changed,
     * not the file.
     *
     * @param  string|array<string, mixed>|null  $value
     */
    public function resolveMedia(string|array|null $value, string $directory, string $nameHint, ?string $alt = null): ?string
    {
        return Media::forUrl($this->resolve($value, $directory, $nameHint), $alt)?->id;
    }

    /**
     * @param  string|array<string, mixed>|null  $value
     */
    public function resolve(string|array|null $value, string $directory, string $nameHint): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return $this->storeBytes(
            $this->readBytes($value, $nameHint),
            $directory,
            $nameHint,
            $this->rawExtensionFor($value),
        );
    }

    public function storeBytes(string $contents, string $directory, string $nameHint, ?string $rawExtension = null): string
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException("Image for '{$nameHint}' is larger than the ".self::MAX_BYTES.' byte limit.');
        }

        $directory = trim($directory, '/');

        /*
         * A name that slugs to nothing - an emoji, a description written in a
         * script Str::slug() cannot transliterate - would leave the filename
         * starting with the separator. The hash is what identifies the file
         * anyway; the words in front of it are for whoever reads the URL.
         */
        $base = (Str::slug($nameHint) ?: 'image').'-'.substr(hash('xxh128', $contents), 0, 12);

        $source = @imagecreatefromstring($contents);

        /*
         * GD is compiled with webp here and on the build box, but not
         * everywhere - and a missing codec raises an Error rather than an
         * exception, which took the whole request down instead of storing a
         * perfectly good picture. Where the encoder is absent the original
         * bytes are kept under their own format; the file is a little larger
         * and every reader still sees it.
         */
        if ($source !== false && function_exists('imagewebp')) {
            ob_start();
            imagewebp($source, null, self::WEBP_QUALITY);
            $webp = (string) ob_get_clean();
            imagedestroy($source);

            $path = $directory.'/'.$base.'.webp';
            $this->disk()->put($path, $webp);

            return $this->disk()->url($path);
        }

        if ($source !== false) {
            imagedestroy($source);
        }

        $extension = $rawExtension ?? $this->extensionFromBytes($contents);

        if ($extension === null) {
            throw new RuntimeException("Could not determine a file extension for the image on '{$nameHint}'; pass 'extension' explicitly.");
        }

        $path = $directory.'/'.$base.'.'.$extension;
        $this->disk()->put($path, $contents);

        return $this->disk()->url($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.runtime_images'));
    }

    private function extensionFromBytes(string $contents): ?string
    {
        $size = @getimagesizefromstring($contents);

        if (! is_array($size)) {
            return null;
        }

        $extension = image_type_to_extension((int) $size[2], false);

        return $extension === false ? null : $extension;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function readBytes(array $value, string $nameHint): string
    {
        if (isset($value['data'])) {
            $decoded = base64_decode((string) $value['data'], true);

            if ($decoded === false) {
                throw new RuntimeException("Image for '{$nameHint}' carries invalid base64 data.");
            }

            return $decoded;
        }

        if (isset($value['source'])) {
            $path = (string) $value['source'];

            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException("Image source '{$path}' for '{$nameHint}' cannot be read.");
            }

            return (string) file_get_contents($path);
        }

        throw new RuntimeException("Image for '{$nameHint}' must be a path string, or an object carrying 'data' or 'source'.");
    }

    /**
     * The extension to fall back on, or null when the entry names none.
     *
     * Answered up front rather than at the point it is needed, because it is a
     * question about the entry and `storeBytes()` only has the bytes. Null is
     * not a failure here - it only becomes one for a payload GD cannot decode,
     * which is where storeBytes() raises it.
     *
     * @param  array<string, mixed>  $value
     */
    private function rawExtensionFor(array $value): ?string
    {
        if (isset($value['extension'])) {
            return strtolower(ltrim((string) $value['extension'], '.'));
        }

        if (isset($value['source'])) {
            $extension = strtolower(pathinfo((string) $value['source'], PATHINFO_EXTENSION));

            if ($extension !== '') {
                return $extension;
            }
        }

        return null;
    }
}
