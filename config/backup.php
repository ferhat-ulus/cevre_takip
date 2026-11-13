<?php

$databaseConnection = env('DB_CONNECTION', 'sqlite');

return [

    'backup' => [
        'name' => env('APP_NAME', 'cevre-takip-backup'),

        'source' => [
            'files' => [
                'include' => array_values(array_filter([
                    base_path(),
                    $databaseConnection === 'sqlite' ? database_path('database.sqlite') : null,
                ])),
                'exclude' => array_values(array_filter([
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('.env'),
                    base_path('.env.backup'),
                    base_path('.env.production'),
                    base_path('.git'),
                    storage_path(),
                    $databaseConnection !== 'sqlite' ? database_path('database.sqlite') : null,
                ])),
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],
            'databases' => in_array($databaseConnection, ['mysql', 'mariadb'], true)
                ? [$databaseConnection]
                : [],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_extension' => '',

        'destination' => [
            'filename_prefix' => '',
            'disks' => [
                'local',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_PASSWORD'),
        'encryption' => 'default',
    ],

    // Bildirimler yalnızca işlem gerektiren yedekleme sorunlarında e-posta gönderir.
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
        'mail' => [
            'to' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')))
            ))),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'cevre-takip-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
