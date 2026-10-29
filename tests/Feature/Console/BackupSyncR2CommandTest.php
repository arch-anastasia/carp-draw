<?php

namespace Tests\Feature\Console;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * The offsite leg of the backup chain.
 *
 * Everything here is about the failure modes that make a backup system look
 * healthy while producing nothing restorable: a dump uploaded while it was
 * still being written, a short upload left in place, a bucket quietly empty
 * because the dump stopped running weeks ago. The upload succeeding is the
 * least interesting thing the command does.
 */
class BackupSyncR2CommandTest extends TestCase
{
    private string $localPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localPath = sys_get_temp_dir().'/carpdraw-backups-'.uniqid();
        mkdir($this->localPath, 0755, true);

        Storage::fake('r2_backups');

        config([
            'backup.disk' => 'r2_backups',
            'backup.prefix' => 'postgres',
            'backup.local_path' => $this->localPath,
            'backup.retention_days' => 30,
            'backup.max_age_hours' => 26,
            'backup.settle_seconds' => 120,
            'filesystems.disks.r2_backups.bucket' => 'carpdraw-backups',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->localPath.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->localPath)) {
            rmdir($this->localPath);
        }

        parent::tearDown();
    }

    public function test_it_uploads_a_settled_dump_that_is_not_in_the_bucket(): void
    {
        $name = $this->writeLocalDump(hoursAgo: 1);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Uploaded '.$name)
            ->assertSuccessful();

        Storage::disk('r2_backups')->assertExists('postgres/'.$name);
        $this->assertSame(
            file_get_contents($this->localPath.'/'.$name),
            Storage::disk('r2_backups')->get('postgres/'.$name),
            'The bytes in the bucket must be the bytes on disk.'
        );
    }

    public function test_it_does_not_re_upload_a_dump_already_offsite(): void
    {
        $name = $this->writeLocalDump(hoursAgo: 1);
        Storage::disk('r2_backups')->put('postgres/'.$name, 'already there');

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Nothing new to upload')
            ->assertSuccessful();

        $this->assertSame('already there', Storage::disk('r2_backups')->get('postgres/'.$name));
    }

    public function test_it_leaves_a_dump_that_is_still_being_written(): void
    {
        $this->seedRemoteDump(hoursAgo: 2);

        // Written seconds ago: the sidecar may still be streaming into it, and
        // half a dump under a name that promises a whole one is worse than no
        // dump at all.
        $name = $this->writeLocalDump(hoursAgo: 0, mtimeAge: 5);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Nothing new to upload')
            ->assertSuccessful();

        Storage::disk('r2_backups')->assertMissing('postgres/'.$name);
    }

    public function test_it_ignores_the_latest_pointer_and_anything_that_is_not_a_dump(): void
    {
        $this->seedRemoteDump(hoursAgo: 2);

        file_put_contents($this->localPath.'/latest.sql.gz', 'pointer copy');
        file_put_contents($this->localPath.'/notes.txt', 'not a backup');
        touch($this->localPath.'/latest.sql.gz', time() - 600);
        touch($this->localPath.'/notes.txt', time() - 600);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Nothing new to upload')
            ->assertSuccessful();

        Storage::disk('r2_backups')->assertMissing('postgres/latest.sql.gz');
        Storage::disk('r2_backups')->assertMissing('postgres/notes.txt');
    }

    public function test_it_prunes_dumps_past_the_retention_window(): void
    {
        $old = $this->seedRemoteDump(hoursAgo: 24 * 40);
        $keep = $this->seedRemoteDump(hoursAgo: 24 * 10);
        $fresh = $this->seedRemoteDump(hoursAgo: 2);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Pruned '.$old)
            ->assertSuccessful();

        Storage::disk('r2_backups')->assertMissing('postgres/'.$old);
        Storage::disk('r2_backups')->assertExists('postgres/'.$keep);
        Storage::disk('r2_backups')->assertExists('postgres/'.$fresh);
    }

    public function test_keep_all_leaves_old_dumps_in_place(): void
    {
        $old = $this->seedRemoteDump(hoursAgo: 24 * 40);
        $this->seedRemoteDump(hoursAgo: 2);

        $this->artisan('backup:sync-r2', ['--keep-all' => true])->assertSuccessful();

        Storage::disk('r2_backups')->assertExists('postgres/'.$old);
    }

    public function test_it_fails_when_the_newest_offsite_dump_is_stale(): void
    {
        $this->seedRemoteDump(hoursAgo: 72);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Freshness check for disk r2_backups bucket carpdraw-backups')
            ->assertFailed();
    }

    public function test_it_fails_when_the_bucket_holds_no_backups_at_all(): void
    {
        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Freshness check found no database backups in disk r2_backups bucket carpdraw-backups')
            ->assertFailed();
    }

    public function test_it_fails_loudly_when_no_bucket_is_configured(): void
    {
        config(['filesystems.disks.r2_backups.bucket' => '']);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('No bucket configured for the r2_backups disk')
            ->assertFailed();
    }

    /**
     * The media bucket is served from a public URL. Putting dumps in it would
     * publish the database, so the sync refuses rather than warns.
     */
    public function test_it_refuses_to_upload_into_the_public_media_bucket(): void
    {
        config([
            'filesystems.disks.public.bucket' => 'carpdraw-media',
            'filesystems.disks.r2_backups.bucket' => 'carpdraw-media',
        ]);

        $name = $this->writeLocalDump(hoursAgo: 1);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('The backup disk is public')
            ->assertFailed();

        Storage::disk('r2_backups')->assertMissing('postgres/'.$name);
    }

    public function test_it_fails_when_the_local_dump_directory_is_not_mounted(): void
    {
        config(['backup.local_path' => $this->localPath.'-does-not-exist']);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Local dump directory '.$this->localPath.'-does-not-exist does not exist')
            ->assertFailed();
    }

    public function test_dry_run_reports_without_touching_the_bucket(): void
    {
        $name = $this->writeLocalDump(hoursAgo: 1);
        $old = $this->seedRemoteDump(hoursAgo: 24 * 40);

        $this->artisan('backup:sync-r2', ['--dry-run' => true])
            ->expectsOutputToContain('Would upload '.$name)
            ->expectsOutputToContain('Would prune '.$old)
            ->assertFailed();

        Storage::disk('r2_backups')->assertMissing('postgres/'.$name);
        Storage::disk('r2_backups')->assertExists('postgres/'.$old);
    }

    /**
     * A stream that dies halfway leaves a short object behind, and a short
     * gzip is a dump that cannot be restored. It must not be left in the
     * bucket looking like a good one.
     */
    public function test_a_failed_stream_is_cleaned_up_and_reported_as_failure(): void
    {
        $name = $this->writeLocalDump(hoursAgo: 1);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('files')->andReturn([]);
        $disk->shouldReceive('writeStream')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->with('postgres/'.$name)->andReturn(true);
        Storage::set('r2_backups', $disk);

        $this->artisan('backup:sync-r2')->assertFailed();
    }

    public function test_a_short_upload_is_deleted_rather_than_kept(): void
    {
        $name = $this->writeLocalDump(hoursAgo: 1);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('files')->andReturn([]);
        $disk->shouldReceive('writeStream')->once()->andReturn(true);
        $disk->shouldReceive('size')->andReturn(7);
        $disk->shouldReceive('delete')->once()->with('postgres/'.$name)->andReturn(true);

        Storage::set('r2_backups', $disk);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Upload failed for local dump '.$this->localPath.'/'.$name)
            ->expectsOutputToContain('disk r2_backups bucket carpdraw-backups')
            ->assertFailed();
    }

    public function test_a_pending_upload_listing_failure_names_the_check_and_paths(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('files')->andThrow(new \RuntimeException('R2 list unavailable'));

        Storage::set('r2_backups', $disk);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Pending-upload check failed for local dump path '.$this->localPath)
            ->expectsOutputToContain('disk r2_backups bucket carpdraw-backups')
            ->assertFailed();
    }

    public function test_a_prune_failure_names_the_operation_target_and_retention(): void
    {
        $name = $this->seedRemoteDump(hoursAgo: 24 * 40);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('files')->andReturn(['postgres/'.$name]);
        $disk->shouldReceive('delete')->once()->andThrow(new \RuntimeException('R2 delete denied'));

        Storage::set('r2_backups', $disk);

        $this->artisan('backup:sync-r2')
            ->expectsOutputToContain('Remote prune failed for disk r2_backups bucket carpdraw-backups with retention 30 days')
            ->assertFailed();
    }

    /**
     * Write a dump into the local directory, named for when it was taken.
     *
     * @param  int  $hoursAgo  how old the dump's data is
     * @param  int  $mtimeAge  seconds since the file was last written
     */
    private function writeLocalDump(int $hoursAgo, int $mtimeAge = 600): string
    {
        $name = 'scheduled-'.CarbonImmutable::now('UTC')->subHours($hoursAgo)->format('Ymd-His').'.sql.gz';
        $path = $this->localPath.'/'.$name;

        file_put_contents($path, 'PGDUMP-'.$name);
        touch($path, time() - $mtimeAge);

        return $name;
    }

    private function seedRemoteDump(int $hoursAgo): string
    {
        $name = 'scheduled-'.CarbonImmutable::now('UTC')->subHours($hoursAgo)->format('Ymd-His').'.sql.gz';

        Storage::disk('r2_backups')->put('postgres/'.$name, 'PGDUMP-'.$name);

        return $name;
    }
}
