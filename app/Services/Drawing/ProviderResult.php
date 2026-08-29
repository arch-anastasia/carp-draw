<?php

namespace App\Services\Drawing;

/**
 * What an image provider hands back: the bytes, and enough provenance to
 * debug a generation months later without keeping the source itself.
 */
final class ProviderResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $contents,
        public readonly string $extension,
        public readonly string $model,
        public readonly array $meta = [],
    ) {}
}
