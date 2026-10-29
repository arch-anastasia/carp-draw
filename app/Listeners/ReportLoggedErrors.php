<?php

namespace App\Listeners;

use App\Services\AnalyticsService;
use App\Services\ErrorReporter;
use Illuminate\Log\Events\MessageLogged;

/**
 * The backstop for `Log::error()`.
 *
 * Every failure the application knows about is meant to go through
 * ErrorReporter. This listener is what makes that true of code that does not
 * - a package writing an error, a call site added later by someone who has
 * not read this file, anything at all that reaches the log at error level and
 * would otherwise have died in storage/logs where nobody is looking.
 *
 * Warnings are deliberately excluded. Laravel and its packages write warnings
 * constantly for conditions that are handled, and forwarding those would bury
 * the errors under them - which is the failure mode this whole subsystem is
 * meant to prevent, arrived at from the opposite direction.
 */
class ReportLoggedErrors
{
    /**
     * PSR-3 levels that mean something is actually broken.
     *
     * @var array<int, string>
     */
    private const REPORTABLE_LEVELS = ['error', 'critical', 'alert', 'emergency'];

    /*
     * No constructor dependencies, deliberately. This listener runs on EVERY
     * log line the application writes - every debug line in local, every info
     * line in production - and injecting the reporter would build it, and the
     * PostHog client behind it, on the first log line of every request that
     * is never going to report anything. The level check below is the hot
     * path; resolution happens only past it.
     */
    public function handle(MessageLogged $event): void
    {
        if (! in_array($event->level, self::REPORTABLE_LEVELS, true)) {
            return;
        }

        /*
         * ErrorReporter stamps every line it writes with this. Without the
         * check, each reported failure would arrive in Sentry twice: once
         * richly, from the reporter, and once again bare, from its own log
         * line coming back around through here.
         */
        if (($event->context['reported'] ?? false) === true) {
            return;
        }

        $exception = $event->context['exception'] ?? null;

        $context = array_merge($event->context, [
            'subsystem' => 'log',
            'log_level' => $event->level,
        ]);

        unset($context['exception']);

        /*
         * A Throwable in the context means Laravel's exception handler put it
         * there, and that handler reported it to Sentry BEFORE writing this
         * line - so Sentry already has it, with a better stack than this
         * listener could assemble. Sending it again through ErrorReporter
         * filed every unhandled exception in Sentry twice: once properly, and
         * once more as `unreported_log_error`.
         *
         * So this branch fills the one gap instead of duplicating the rest:
         * PostHog, which the exception handler knows nothing about. Same
         * split as ReportFailedJobs, for the same reason.
         */
        if ($exception instanceof \Throwable) {
            app(AnalyticsService::class)->captureException($exception, null, array_merge($context, [
                'error_code' => 'unhandled_exception',
            ]));

            return;
        }

        /*
         * A bare `Log::error()` with no exception behind it reaches neither
         * destination on its own, so this one goes the full route.
         */
        app(ErrorReporter::class)->reportIssue('unreported_log_error', $event->message, $context);
    }
}
