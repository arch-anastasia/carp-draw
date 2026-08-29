<?php

namespace App\Listeners;

use App\Services\AnalyticsService;
use Illuminate\Queue\Events\JobFailed;

/**
 * Every queued job that dies, reported to PostHog.
 *
 * Sentry already has these: the worker hands a failed job's exception to the
 * application's exception handler, and Sentry is wired into that handler in
 * bootstrap/app.php. Reporting them again here would double every entry, so
 * this listener covers the half Sentry does not - PostHog error tracking,
 * where the crash sits next to the funnel it interrupted.
 *
 * This is a NET, not the primary report. RenderLook and DescribeReference
 * report their own failures with the render's own context, which is far more
 * useful; this catches the jobs that have no such handling and the ones whose
 * worker was killed before their `failed()` hook could run.
 */
class ReportFailedJobs
{
    public function __construct(private AnalyticsService $analytics) {}

    public function handle(JobFailed $event): void
    {
        $job = $event->job->resolveName();

        $this->analytics->captureException($event->exception, null, [
            'error_code' => 'queue_job_failed',
            'subsystem' => 'queue',
            'job' => $job,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
        ]);
    }
}
