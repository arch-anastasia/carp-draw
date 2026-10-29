<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Opt-in until the private bucket / Stripe account have been configured.
Schedule::command('backup:sync-r2')->hourly()->withoutOverlapping()
    ->when(fn (): bool => (bool) config('backup.enabled'));
Schedule::command('billing:check')->daily()->withoutOverlapping()
    ->when(fn (): bool => (bool) config('operations.billing_check_enabled'));
