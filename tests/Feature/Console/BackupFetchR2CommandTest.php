<?php

namespace Tests\Feature\Console;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Getting a dump back out of the bucket.
 *
 * The half of a backup system that is usually only exercised on the worst day
 * of the year, which is exactly why it is worth a test on the other 364.
 */
class BackupFetchR2CommandTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->destination = sys_get_temp_dir().'/hsa-restore-'.uniqid();

        Storage::fake('r2_backups');

        config([
            'backup.disk' => 'r2_backups',
            'backup.prefix' => 'postgres',
            'filesystems.disks.r2_backups.bucket' => 'carpdraw-backups',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->destination.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->destination)) {
            rmdir($this->destination);
        }

        parent::tearDown();
    }

    public function test_it_fetches_the_newest_dump_by_default(): void
    {
        $this->seedRemoteDump(hoursAgo: 72);
        $newest = $this->seedRemoteDump(hoursAgo: 2);

        $this->artisan('backup:fetch-r2', ['--to' => $this->destination])
            ->expectsOutputToContain($newest)
            ->assertSuccessful();

        $this->assertFileExists($this->destination.'/'.$newest);
        $this->assertSame('PGDUMP-'.$newest, file_get_contents($this->destination.'/'.$newest));
    }

    public function test_it_fetches_a_named_dump(): void
    {
        $wanted = $this->seedRemoteDump(hoursAgo: 72);
        $newest = $this->seedRemoteDump(hoursAgo: 2);

        $this->artisan('backup:fetch-r2', ['name' => $wanted, '--to' => $this->destination])
            ->assertSuccessful();

        $this->assertFileExists($this->destination.'/'.$wanted);
        $this->assertFileDoesNotExist($this->destination.'/'.$newest);
    }

    public function test_it_creates_the_destination_directory(): void
    {
        $newest = $this->seedRemoteDump(hoursAgo: 2);
        $nested = $this->destination.'/nested';

        $this->artisan('backup:fetch-r2', ['--to' => $nested])->assertSuccessful();

        $this->assertFileExists($nested.'/'.$newest);

        unlink($nested.'/'.$newest);
        rmdir($nested);
    }

    public function test_it_fails_for_a_dump_that_is_not_in_the_bucket(): void
    {
        $this->seedRemoteDump(hoursAgo: 2);

        $this->artisan('backup:fetch-r2', [
            'name' => 'scheduled-20200101-000000.sql.gz',
            '--to' => $this->destination,
        ])
            ->expectsOutputToContain('is not in the backup bucket')
            ->assertFailed();
    }

    public function test_it_fails_when_the_bucket_is_empty(): void
    {
        $this->artisan('backup:fetch-r2', ['--to' => $this->destination])
            ->expectsOutputToContain('empty')
            ->assertFailed();
    }

    public function test_it_lists_what_is_in_the_bucket(): void
    {
        $older = $this->seedRemoteDump(hoursAgo: 72);
        $newer = $this->seedRemoteDump(hoursAgo: 2);

        $this->artisan('backup:fetch-r2', ['--list' => true])
            ->expectsOutputToContain($older)
            ->expectsOutputToContain($newer)
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->destination.'/'.$newer);
    }

    public function test_it_fails_loudly_when_no_bucket_is_configured(): void
    {
        config(['filesystems.disks.r2_backups.bucket' => '']);

        $this->artisan('backup:fetch-r2', ['--to' => $this->destination])
            ->expectsOutputToContain('R2_BACKUP_BUCKET')
            ->assertFailed();
    }

    private function seedRemoteDump(int $hoursAgo): string
    {
        $name = 'scheduled-'.CarbonImmutable::now('UTC')->subHours($hoursAgo)->format('Ymd-His').'.sql.gz';

        Storage::disk('r2_backups')->put('postgres/'.$name, 'PGDUMP-'.$name);

        return $name;
    }
}
