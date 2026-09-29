<?php

namespace Tests\Feature;

use App\Enums\WorkflowMode;
use App\Jobs\GenerateDrawing;
use App\Models\Project;
use App\Models\User;
use App\Services\Drawing\CreditLedger;
use App\Services\Drawing\DrawingFailed;
use App\Services\Drawing\ProjectService;
use App\Services\Storage\PrivateDiskGuard;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FoundationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_account_cannot_refill_a_settled_month_twice(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 15)->startOfDay());
        $user = $this->createSubscribedUser(['credits_period_started_at' => now()->subMonth()]);
        $stale = User::findOrFail($user->id);
        $service = app(ProjectService::class);
        $allowance = $service->availableCredits($user);
        app(CreditLedger::class)->spend($user, 4, Project::factory()->for($user)->create());

        $this->assertSame($allowance - 4, $service->availableCredits($stale));
        $this->assertTrue($stale->credits_period_started_at->equalTo(now()));
    }

    public function test_month_end_renews_on_february_end_and_only_once_at_exact_boundary(): void
    {
        $this->travelTo(now()->setDate(2026, 2, 28)->startOfDay());
        $user = $this->createSubscribedUser(['credits_period_started_at' => now()->setDate(2026, 1, 31)]);
        $service = app(ProjectService::class);
        $allowance = $service->availableCredits($user);
        $this->assertSame($service->allowanceFor($user), $allowance);
        $this->assertTrue($user->credits_period_started_at->equalTo(now()));
        app(CreditLedger::class)->spend($user, 4, Project::factory()->for($user)->create());
        $this->assertSame($allowance - 4, $service->availableCredits($user));
    }

    public function test_refunds_cannot_credit_the_wrong_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $ledger = app(CreditLedger::class);
        $ledger->spend($owner, 4, $project);

        $this->assertNull($ledger->refund($other, $project));
        $this->assertSame(12, $other->creditBalance());
        $this->assertSame(8, $owner->creditBalance());
    }

    public function test_repeated_opening_grants_do_not_mint_more_credits(): void
    {
        $user = User::factory()->create();
        $this->assertNull(app(CreditLedger::class)->grantInitialAllowance($user, 12));
        $this->assertSame(12, $user->creditBalance());
    }

    public function test_failed_source_storage_neither_charges_nor_queues(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        try {
            app(ProjectService::class)->create($user, WorkflowMode::Drawing, UploadedFile::fake()->image('chair.jpg'));
            $this->fail('A failed source upload must not create a project.');
        } catch (DrawingFailed $exception) {
            $this->assertSame('The upload could not be saved. Your credits are untouched.', $exception->getMessage());
        }

        $this->assertSame(12, $user->creditBalance());
        $this->assertDatabaseCount('projects', 0);
        Queue::assertNothingPushed();
    }

    public function test_new_projects_remember_their_disk_across_configuration_changes(): void
    {
        Queue::fake();
        Storage::fake('local');
        $project = app(ProjectService::class)->create(User::factory()->create(), WorkflowMode::Drawing, UploadedFile::fake()->image('chair.jpg'));
        config(['drawing.provider.disk' => 'r2_drawings']);

        $this->assertSame('local', $project->fresh()->disk());
        $this->assertSame('local', Project::factory()->create(['storage_disk' => null])->disk());
        Storage::disk('local')->assertExists($project->source_path);
        Queue::assertPushed(GenerateDrawing::class);
    }

    public function test_a_private_alias_of_a_public_bucket_is_rejected(): void
    {
        config([
            'filesystems.disks.s3.bucket' => 'editorial',
            'filesystems.disks.s3.url' => 'https://assets.example.com',
            'filesystems.disks.r2_drawings.bucket' => 'editorial',
        ]);
        $this->expectException(RuntimeException::class);
        app(PrivateDiskGuard::class)->assertPrivate('r2_drawings');
    }

    public function test_public_local_disk_is_rejected_for_private_files(): void
    {
        $this->expectException(RuntimeException::class);
        app(PrivateDiskGuard::class)->assertPrivate('public');
    }

    public function test_private_files_cannot_live_in_a_subdirectory_of_the_public_disk(): void
    {
        config(['filesystems.disks.private_alias' => [
            'driver' => 'local',
            'root' => config('filesystems.disks.public.root').'/private-looking-name',
        ]]);
        $this->expectException(RuntimeException::class);
        app(PrivateDiskGuard::class)->assertPrivate('private_alias');
    }

    public function test_browser_monitoring_config_never_exposes_the_server_dsn(): void
    {
        config(['sentry.dsn' => 'private-server-dsn', 'sentry.browser.dsn' => 'https://public@example.com/1']);
        $response = $this->get('/');
        $response->assertOk()->assertDontSee('private-server-dsn')->assertSee('browser-telemetry');
        $this->assertStringContainsString('https://example.com', $response->headers->get('Content-Security-Policy'));
    }
}
