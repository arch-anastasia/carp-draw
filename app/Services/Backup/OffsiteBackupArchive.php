<?php

namespace App\Services\Backup;

use App\Services\Storage\PrivateDiskGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The offsite half of the backup chain.
 *
 * The `postgres-backup` sidecar in docker-compose.prod.yml owns making the
 * dump: it runs pg_dump from a postgres:17-alpine image (so the client version
 * always matches the server), verifies the gzip stream and the trailing
 * "PostgreSQL database dump complete" marker, and deletes anything that fails
 * either check. Only dumps that survived that live in the local directory.
 *
 * This class owns getting those dumps off the box, which is the half that
 * matters when the box is what you lost.
 */
class OffsiteBackupArchive
{
    /**
     * Dumps the sidecar writes, e.g. `scheduled-20260820-030000.sql.gz`.
     */
    private const DUMP_PATTERN = 'scheduled-*.sql.gz';

    private const STAMP_FORMAT = 'Ymd-His';

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('backup.disk');
    }

    public function bucketName(): string
    {
        return trim((string) config('filesystems.disks.'.$this->diskName().'.bucket'));
    }

    /**
     * Whether a bucket has actually been configured.
     *
     * Checked before anything else so a half-filled .env fails loudly on the
     * first scheduled run rather than quietly uploading nowhere for a month.
     */
    public function isConfigured(): bool
    {
        return $this->bucketName() !== '';
    }

    /**
     * Whether the backup bucket is the same one the site serves media from.
     *
     * The media bucket is bound to a public URL. A dump of every user row
     * sitting in it is readable by anyone who guesses the key, so this is not
     * a preference to be documented - it is a condition the sync refuses to
     * run under.
     */
    public function sharesBucketWithPublicMedia(): bool
    {
        try {
            app(PrivateDiskGuard::class)->assertPrivate($this->diskName());

            return false;
        } catch (RuntimeException) {
            return true;
        }
    }

    public function localPath(): string
    {
        return rtrim((string) config('backup.local_path'), '/');
    }

    public function prefix(): string
    {
        return trim((string) config('backup.prefix'), '/');
    }

    public function key(string $name): string
    {
        $prefix = $this->prefix();

        return $prefix === '' ? $name : $prefix.'/'.$name;
    }

    /**
     * Local dumps that have finished being written.
     *
     * A file the sidecar is still streaming into has a mtime of seconds ago.
     * Uploading that puts a truncated dump offsite under a name that claims to
     * be a complete one - the worst possible outcome, because it looks like a
     * backup right up until you need it.
     *
     * @return list<string> absolute paths, oldest first
     */
    public function settledLocalDumps(): array
    {
        $paths = glob($this->localPath().'/'.self::DUMP_PATTERN) ?: [];
        $cutoff = CarbonImmutable::now()->getTimestamp() - (int) config('backup.settle_seconds');

        $settled = array_values(array_filter(
            $paths,
            fn (string $path): bool => is_file($path) && filemtime($path) <= $cutoff
        ));

        sort($settled);

        return $settled;
    }

    /**
     * Dump names already in the bucket.
     *
     * @return list<string> basenames, oldest first
     */
    public function remoteDumps(): array
    {
        $names = array_map(
            fn (string $path): string => basename($path),
            $this->disk()->files($this->prefix())
        );

        $names = array_values(array_filter(
            $names,
            fn (string $name): bool => fnmatch(self::DUMP_PATTERN, $name)
        ));

        sort($names);

        return $names;
    }

    /**
     * Local dumps that have not reached the bucket yet.
     *
     * @return list<string> absolute paths, oldest first
     */
    public function pendingUploads(): array
    {
        $remote = array_flip($this->remoteDumps());

        return array_values(array_filter(
            $this->settledLocalDumps(),
            fn (string $path): bool => ! isset($remote[basename($path)])
        ));
    }

    /**
     * Stream one dump into the bucket and prove it arrived whole.
     *
     * The size check is not ceremony: a stream that dies halfway leaves a
     * short object behind, and a short gzip is a dump that cannot be restored.
     * A partial upload is deleted rather than left to be mistaken for a good
     * one.
     *
     * @return int bytes uploaded
     */
    public function upload(string $localPath): int
    {
        $name = basename($localPath);
        $key = $this->key($name);
        $expected = (int) filesize($localPath);

        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Could not open {$localPath} for reading.");
        }

        try {
            if (! $this->disk()->writeStream($key, $stream)) {
                throw new RuntimeException("Upload of {$name} failed.");
            }

            $actual = (int) $this->disk()->size($key);
            if ($actual !== $expected) {
                throw new RuntimeException(
                    "Upload of {$name} is short: expected {$expected} bytes, bucket has {$actual}."
                );
            }

            return $actual;
        } catch (Throwable $exception) {
            // A failed stream may still leave a partial object. Otherwise the
            // next sync would mistake its name for an already completed dump.
            try {
                $this->disk()->delete($key);
            } catch (Throwable) {
                // Preserve the original storage failure for the operator.
            }
            throw $exception;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Drop dumps past the retention window.
     *
     * @return list<string> names deleted
     */
    public function prune(): array
    {
        $retentionDays = (int) config('backup.retention_days');

        if ($retentionDays <= 0) {
            return [];
        }

        $cutoff = CarbonImmutable::now('UTC')->subDays($retentionDays);
        $deleted = [];

        foreach ($this->remoteDumps() as $name) {
            $takenAt = $this->takenAt($name);

            if ($takenAt === null || $takenAt->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            $this->disk()->delete($this->key($name));
            $deleted[] = $name;
        }

        return $deleted;
    }

    /**
     * When the newest offsite dump was taken, or null if the bucket is empty.
     */
    public function newestRemoteTakenAt(): ?CarbonImmutable
    {
        $newest = null;

        foreach ($this->remoteDumps() as $name) {
            $takenAt = $this->takenAt($name);

            if ($takenAt !== null && ($newest === null || $takenAt->greaterThan($newest))) {
                $newest = $takenAt;
            }
        }

        return $newest;
    }

    public function newestRemoteDump(): ?string
    {
        $names = $this->remoteDumps();

        return $names === [] ? null : end($names);
    }

    /**
     * Pull one dump back down, for a restore.
     *
     * @return int bytes written
     */
    public function download(string $name, string $destination): int
    {
        $key = $this->key($name);

        if (! $this->disk()->exists($key)) {
            throw new RuntimeException("{$name} is not in the backup bucket.");
        }

        $source = $this->disk()->readStream($key);

        if (! is_resource($source)) {
            throw new RuntimeException("Could not read {$name} from the backup bucket.");
        }

        $target = fopen($destination, 'wb');

        if ($target === false) {
            throw new RuntimeException("Could not open {$destination} for writing.");
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }

        $written = (int) filesize($destination);
        $expected = (int) $this->disk()->size($key);

        if ($written !== $expected) {
            throw new RuntimeException(
                "Download of {$name} is short: bucket has {$expected} bytes, wrote {$written}."
            );
        }

        return $written;
    }

    /**
     * The timestamp baked into a dump's name.
     *
     * Read from the name rather than the object's mtime on purpose: mtime is
     * when the object was written to R2, so re-uploading an old dump would
     * make it look fresh and reset its retention clock. The name is when the
     * data is actually from, which is the only thing a restore cares about.
     */
    public function takenAt(string $name): ?CarbonImmutable
    {
        if (preg_match('/^scheduled-(\d{8}-\d{6})\.sql\.gz$/', basename($name), $matches) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat(self::STAMP_FORMAT, $matches[1], 'UTC');

        return $parsed;
    }
}
