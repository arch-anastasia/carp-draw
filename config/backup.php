<?php

return [

    // Explicitly enabled only after dump generation and private storage are configured.
    'enabled' => (bool) env('BACKUP_SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Offsite disk
    |--------------------------------------------------------------------------
    |
    | Where verified dumps are shipped. This is deliberately NOT the public editorial disk:
    | that one is the media bucket, it is bound to a public URL, and a database
    | dump under a guessable key on a public bucket is a breach, not a backup.
    |
    */

    'disk' => env('BACKUP_DISK', 'r2_backups'),

    /*
    |--------------------------------------------------------------------------
    | Key prefix
    |--------------------------------------------------------------------------
    |
    | Folder inside the bucket. Keeping dumps under one prefix means the sync
    | command can list only what it owns rather than the whole bucket.
    |
    */

    'prefix' => env('BACKUP_PREFIX', 'postgres'),

    /*
    |--------------------------------------------------------------------------
    | Local dump directory
    |--------------------------------------------------------------------------
    |
    | Written by the opt-in verified PostgreSQL dump script and
    | mounted read-only into the scheduler container. Only files the sidecar has already verified ever appear here.
    |
    */

    'local_path' => env('BACKUP_LOCAL_PATH', base_path('backups')),

    /*
    |--------------------------------------------------------------------------
    | Remote retention
    |--------------------------------------------------------------------------
    |
    | Days of dumps to keep in the bucket. Longer than the sidecar's local
    | BACKUP_RETENTION_DAYS (7) on purpose - the offsite copy is the one that
    | survives losing the box, so it is the one worth paying to keep.
    |
    */

    'retention_days' => (int) env('BACKUP_REMOTE_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Staleness threshold
    |--------------------------------------------------------------------------
    |
    | How old the newest offsite dump may get before the sync command starts
    | failing. Slightly over a day, so a backup running a little late is not an
    | alert but a backup that did not run at all is.
    |
    | A backup nobody is watching is a backup you find out about during the
    | restore, so this check is the point of the whole command.
    |
    */

    'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),

    /*
    |--------------------------------------------------------------------------
    | Settling period
    |--------------------------------------------------------------------------
    |
    | Seconds a dump must have sat untouched before it is considered finished.
    | The sidecar writes straight into the directory, so a file whose mtime is
    | seconds old may still be growing; uploading it would put a truncated dump
    | offsite under a name that claims to be a complete one.
    |
    */

    'settle_seconds' => (int) env('BACKUP_SETTLE_SECONDS', 120),

];
