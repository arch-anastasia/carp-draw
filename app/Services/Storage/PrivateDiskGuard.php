<?php

namespace App\Services\Storage;

use RuntimeException;

/** Private customer files and database dumps must never use the editorial bucket. */
class PrivateDiskGuard
{
    public function assertPrivate(string $name): void
    {
        $disk = config('filesystems.disks.'.$name);
        $public = (string) config('filesystems.runtime_images');

        if (! is_array($disk) || $name === $public || filled($disk['url'] ?? null) || ($disk['visibility'] ?? null) === 'public') {
            throw new RuntimeException('A private storage disk is required.');
        }

        foreach (config('filesystems.disks', []) as $otherName => $other) {
            $isPublic = $otherName === $public || filled($other['url'] ?? null) || ($other['visibility'] ?? null) === 'public';
            if (! $isPublic) {
                continue;
            }

            if (filled($disk['bucket'] ?? null) && $disk['bucket'] === ($other['bucket'] ?? null)) {
                throw new RuntimeException('Private files cannot share a public bucket.');
            }

            $publicRoot = rtrim($other['root'] ?? '', '/');
            $privateRoot = rtrim($disk['root'] ?? '', '/');
            if ($publicRoot !== '' && $privateRoot !== '' && str_starts_with($privateRoot.'/', $publicRoot.'/')) {
                throw new RuntimeException('Private files cannot share a public directory.');
            }
        }
    }
}
