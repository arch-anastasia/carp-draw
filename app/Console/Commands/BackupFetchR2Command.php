<?php

namespace App\Console\Commands;

use App\Services\Backup\OffsiteBackupArchive;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulls a dump back down from the offsite bucket.
 *
 * The counterpart to backup:sync-r2, and the reason the sync command is worth
 * anything: a backup you have never fetched is a hypothesis. Restoring the
 * downloaded file is a deliberate manual step - see docs/production-deployment.md.
 */
class BackupFetchR2Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:fetch-r2
                            {name? : Dump to fetch, e.g. scheduled-20260820-030000.sql.gz (default: the newest)}
                            {--to= : Directory to write it to (default: storage/app/private/backups)}
                            {--list : List what is in the bucket instead of downloading}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download a Postgres dump from the offsite backup bucket so it can be restored';

    /**
     * Execute the console command.
     */
    public function handle(OffsiteBackupArchive $archive): int
    {
        if (! $archive->isConfigured()) {
            $this->error('No bucket configured for the '.$archive->diskName().' disk. Set R2_BACKUP_BUCKET (see .env.example).');

            return self::FAILURE;
        }

        try {
            if ($this->option('list')) {
                return $this->listDumps($archive);
            }

            $name = (string) ($this->argument('name') ?? $archive->newestRemoteDump());

            if ($name === '') {
                $this->error('The backup bucket is empty.');

                return self::FAILURE;
            }

            $directory = rtrim((string) ($this->option('to') ?: storage_path('app/private/backups')), '/');

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                $this->error('Could not create '.$directory.'.');

                return self::FAILURE;
            }

            $destination = $directory.'/'.basename($name);
            $bytes = $archive->download($name, $destination);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Wrote '.$destination.' ('.number_format($bytes).' bytes).');
        $this->line('Restore with: gzip -dc '.$destination.' | psql "$DATABASE_URL"');

        return self::SUCCESS;
    }

    private function listDumps(OffsiteBackupArchive $archive): int
    {
        $names = $archive->remoteDumps();

        if ($names === []) {
            $this->error('The backup bucket is empty.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now('UTC');

        $this->table(['Dump', 'Taken (UTC)', 'Age'], array_map(function (string $name) use ($archive, $now): array {
            $takenAt = $archive->takenAt($name);

            return [
                $name,
                $takenAt?->toDateTimeString() ?? 'unknown',
                $takenAt === null ? 'unknown' : (int) $takenAt->diffInHours($now).'h',
            ];
        }, $names));

        return self::SUCCESS;
    }
}
