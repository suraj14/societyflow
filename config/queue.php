<?php

/*
 * This file never existed in this repository. Without it `queue.default`
 * is null, and Laravel resolves a null connection to Illuminate\Queue\NullQueue,
 * which silently discards every job - so every DispatchEventNotification
 * (welcome, payment, notice, ticket, visitor, bill, access pass, facility
 * booking, society document emails) was dropped whatever QUEUE_CONNECTION
 * said, with or without a worker. Tests did not notice because they fake
 * the bus. See tests/Feature/QueueConfigurationTest.php.
 *
 * The `jobs`, `job_batches` and `failed_jobs` tables already exist
 * (2026_10_07_000003_add_delivery_pipeline_columns_and_queue_tables).
 * Production: QUEUE_CONNECTION=database plus a `php artisan queue:work`
 * worker (docs/PRODUCTION_CHECKLIST.md). `sync` sends inline.
 */
return [

    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
