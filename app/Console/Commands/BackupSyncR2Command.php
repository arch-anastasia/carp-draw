<?php

namespace App\Console\Commands;

use App\Services\Backup\OffsiteBackupArchive;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Ships verified Postgres dumps to Cloudflare R2 and keeps the bucket tidy.
 *
 * Runs hourly rather than once a night. The dump itself happens once, at
 * BACKUP_HOUR_UTC; running the upload hourly costs one LIST call when there is
 * nothing to do and means a bucket that was unreachable at 03:00 is caught up
 * by 04:00 instead of losing the day.
 */
class BackupSyncR2Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:sync-r2
                            {--keep-all : Upload without deleting dumps past the retention window}
                            {--dry-run : Report what would be uploaded and pruned without touching the bucket}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload verified Postgres dumps to the offsite backup bucket, prune old ones, and fail if the newest is stale';

    /**
     * Execute the console command.
     */
    public function handle(OffsiteBackupArchive $archive): int
    {
        if (! $archive->isConfigured()) {
            return $this->reportFailure(
                'No bucket configured for the '.$archive->diskName().' disk. Set R2_BACKUP_BUCKET (see .env.example).'
            );
        }

        if ($archive->sharesBucketWithPublicMedia()) {
            return $this->reportFailure(
                'The backup disk is public or shares storage with public media. Refusing to upload database dumps to it.'
            );
        }

        if (! is_dir($archive->localPath())) {
            return $this->reportFailure(
                'Local dump directory '.$archive->localPath().' does not exist. Mount verified dumps from scripts/backup-postgres.sh read-only into the scheduler.'
            );
        }

        $dryRun = (bool) $this->option('dry-run');
        $failed = $this->upload($archive, $dryRun);

        if (! $this->option('keep-all')) {
            $failed = $this->prune($archive, $dryRun) || $failed;
        }

        $failed = $this->checkFreshness($archive) || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return bool whether anything failed
     */
    private function upload(OffsiteBackupArchive $archive, bool $dryRun): bool
    {
        try {
            $pending = $archive->pendingUploads();
        } catch (Throwable $exception) {
            $this->reportFailure(
                'Pending-upload check failed for local dump path '.$archive->localPath().' against '.$this->remoteTarget($archive).': '.$exception->getMessage(),
                $exception
            );

            return true;
        }

        if ($pending === []) {
            $this->line('Nothing new to upload.');

            return false;
        }

        $failed = false;

        foreach ($pending as $path) {
            $name = basename($path);

            if ($dryRun) {
                $this->line('Would upload '.$name.' ('.$this->humanSize((int) filesize($path)).').');

                continue;
            }

            try {
                $bytes = $archive->upload($path);
                $this->info('Uploaded '.$name.' ('.$this->humanSize($bytes).').');
            } catch (Throwable $exception) {
                $failed = true;
                $this->reportFailure(
                    'Upload failed for local dump '.$path.' to '.$this->remoteTarget($archive).': '.$exception->getMessage(),
                    $exception
                );
            }
        }

        return $failed;
    }

    /**
     * @return bool whether anything failed
     */
    private function prune(OffsiteBackupArchive $archive, bool $dryRun): bool
    {
        $retentionDays = (int) config('backup.retention_days');

        try {
            if ($dryRun) {
                $cutoff = CarbonImmutable::now('UTC')->subDays($retentionDays);

                foreach ($archive->remoteDumps() as $name) {
                    $takenAt = $archive->takenAt($name);

                    if ($takenAt !== null && $takenAt->lessThan($cutoff)) {
                        $this->line('Would prune '.$name.'.');
                    }
                }

                return false;
            }

            foreach ($archive->prune() as $name) {
                $this->line('Pruned '.$name.' (older than '.$retentionDays.' days).');
            }
        } catch (Throwable $exception) {
            $this->reportFailure(
                'Remote prune failed for '.$this->remoteTarget($archive).' with retention '.$retentionDays.' days: '.$exception->getMessage(),
                $exception
            );

            return true;
        }

        return false;
    }

    /**
     * The check that gives the whole command a reason to exist.
     *
     * Uploading successfully proves nothing if there was nothing to upload
     * because the dump stopped running three weeks ago. This is the assertion
     * that a usable offsite copy exists right now.
     *
     * @return bool whether anything failed
     */
    private function checkFreshness(OffsiteBackupArchive $archive): bool
    {
        $maxAgeHours = (int) config('backup.max_age_hours');

        try {
            $newest = $archive->newestRemoteTakenAt();
        } catch (Throwable $exception) {
            $this->reportFailure(
                'Freshness check failed for '.$this->remoteTarget($archive).' (limit '.$maxAgeHours.'h): '.$exception->getMessage(),
                $exception
            );

            return true;
        }

        if ($newest === null) {
            $this->reportFailure(
                'Freshness check found no database backups in '.$this->remoteTarget($archive).' (limit '.$maxAgeHours.'h; local dump path '.$archive->localPath().').'
            );

            return true;
        }

        $ageHours = (int) $newest->diffInHours(CarbonImmutable::now('UTC'));

        if ($ageHours > $maxAgeHours) {
            $this->reportFailure(
                'Freshness check for '.$this->remoteTarget($archive).': newest offsite backup is '.$ageHours.'h old (limit '.$maxAgeHours.'h): '.$newest->toDateTimeString().' UTC.'
            );

            return true;
        }

        $this->info('Newest offsite backup is '.$ageHours.'h old ('.$newest->toDateTimeString().' UTC).');

        return false;
    }

    /**
     * Shout into both the scheduler log and Sentry, then hand back FAILURE.
     */
    private function reportFailure(string $message, ?Throwable $previous = null): int
    {
        $this->error($message);
        report(new RuntimeException('Database backup: '.$message, 0, $previous));

        return self::FAILURE;
    }

    private function remoteTarget(OffsiteBackupArchive $archive): string
    {
        return 'disk '.$archive->diskName().' bucket '.$archive->bucketName();
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.'B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 1).$units[$unit];
    }
}
