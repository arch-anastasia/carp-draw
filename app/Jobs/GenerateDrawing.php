<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Drawing\CreditLedger;
use App\Services\Drawing\DrawingFailed;
use App\Services\Drawing\ImageProvider;
use App\Services\Drawing\PromptBuilder;
use App\Services\Drawing\ProviderRegistry;
use App\Services\ErrorReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * One queued generation per project.
 *
 * Timeout invariant when the queue is redis: provider timeout < job timeout
 * < worker --timeout < retry_after — otherwise a slow generation is handed
 * to a second worker: two API calls, one credit.
 */
class GenerateDrawing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    public function __construct(public string $projectId)
    {
        $this->timeout = max(30, (int) config('drawing.provider.job_timeout', 75));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 45];
    }

    public function handle(
        ImageProvider $provider,
        ProviderRegistry $registry,
        PromptBuilder $prompts,
        CreditLedger $ledger,
        ErrorReporter $reporter,
    ): void {
        $project = Project::query()->with('user')->find($this->projectId);

        // Deleted, or already finished by an earlier attempt: nothing to do.
        if (! $project || $project->status->isFinished()) {
            return;
        }

        $project->forceFill([
            'status' => ProjectStatus::Processing,
            'attempts' => $project->attempts + 1,
            'provider' => $registry->effectiveDriver(),
        ])->save();

        $disk = Storage::disk($project->disk());

        try {
            if (! $project->source_path || ! $disk->exists($project->source_path)) {
                throw DrawingFailed::permanent('missing_source', 'The uploaded source file is gone.');
            }

            $result = $provider->edit(
                (string) $disk->get($project->source_path),
                (string) ($project->source_filename ?: 'source'),
                $prompts->buildFor($project),
                $project->workflow,
            );

            $outputPath = "drawings/{$project->user_id}/{$project->id}/output.{$result->extension}";

            // Checked: the disks are configured throw=>false, so a silent
            // write failure would mark a project Succeeded with no file
            // behind it — and never refund.
            if (! $disk->put($outputPath, $result->contents)) {
                throw DrawingFailed::retryable('storage_failed', 'The generated sheet could not be stored.');
            }

            $project->forceFill([
                'status' => ProjectStatus::Succeeded,
                'output_path' => $outputPath,
                'output_format' => $result->extension,
                'provider_model' => $result->model,
                'error_code' => null,
                'error_message' => null,
                'completed_at' => now(),
            ])->save();
        } catch (DrawingFailed $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $reporter->warn('drawing_retried', $e->getMessage(), [
                    'subsystem' => 'drawing',
                    'project_id' => $project->id,
                    'failure_code' => $e->errorCode,
                    'attempt' => $this->attempts(),
                ], $project->user);

                // Row stays `processing` so the poller doesn't flicker.
                $this->release($this->backoff()[min($this->attempts(), count($this->backoff())) - 1] ?? 45);

                return;
            }

            $this->failProject($project, $e, $ledger, $reporter);
        } catch (\Throwable $e) {
            $this->failProject(
                $project,
                DrawingFailed::permanent('unexpected', $e->getMessage()),
                $ledger,
                $reporter,
                $e,
            );
        }
    }

    /**
     * Covers worker death and retry exhaustion — the same contract as an
     * in-band failure: the visitor is never charged for a drawing that did
     * not arrive.
     */
    public function failed(?\Throwable $exception): void
    {
        $project = Project::query()->with('user')->find($this->projectId);

        if (! $project || $project->status->isFinished()) {
            return;
        }

        $this->failProject(
            $project,
            DrawingFailed::permanent('worker_died', $exception?->getMessage() ?? 'The generation was interrupted.'),
            app(CreditLedger::class),
            app(ErrorReporter::class),
            $exception,
        );
    }

    private function failProject(
        Project $project,
        DrawingFailed $failure,
        CreditLedger $ledger,
        ErrorReporter $reporter,
        ?\Throwable $original = null,
    ): void {
        /*
         * Refund FIRST, then mark failed. Marking first made the refund
         * unreachable forever if its write threw: every re-entry path
         * (retry, failed() hook) early-returns on isFinished(). In this
         * order a thrown refund leaves the project unfinished, so the next
         * attempt lands back here — and the ledger-derived amount makes a
         * second pass refund nothing twice.
         */
        $ledger->refund($project->user, $project);

        $project->forceFill([
            'status' => ProjectStatus::Failed,
            'error_code' => $failure->errorCode,
            // Visitor-safe copy only — never provider detail.
            'error_message' => $failure->visitorMessage(),
            'credits_spent' => 0,
            'completed_at' => now(),
        ])->save();

        if (! $failure->isExpectedUserRejection()) {
            $reporter->report($original ?? $failure, 'drawing_failed', [
                'subsystem' => 'drawing',
                'project_id' => $project->id,
                'workflow' => $project->workflow->value,
                'failure_code' => $failure->errorCode,
                'retryable' => $failure->retryable,
                'attempts' => $project->attempts,
            ], $project->user);
        }
    }
}
