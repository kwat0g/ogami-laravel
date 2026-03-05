<?php

return [

    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'ogami-erp'),

        'source' => [
            'files' => [
                /*
                 * For this ERP only the uploaded 201 files are worth including.
                 * Set BACKUP_INCLUDE_FILES=true to add storage/app/public to the
                 * backup archive. Default = false (DB-only) for faster daily runs.
                 */
                'include' => env('BACKUP_INCLUDE_FILES', false)
                    ? [storage_path('app/public')]
                    : [],

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('app/backup-temp'),
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => true,

                'relative_path' => null,
            ],

            /*
             * Only back up the primary DB connection.
             */
            'databases' => [
                env('DB_CONNECTION', 'pgsql'),
            ],
        ],

        /*
         * Use gzip compression on the DB dump itself.
         * Because the dump is already compressed, the ZIP wrapper uses STORE (no-op).
         */
        'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'
         *
         * If 'database' (default), the dumped filename will contain the database name.
         * If 'connection', the dumped filename will contain the connection name.
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         *
         * If not specified, the file extension will be .archive for MongoDB and .sql for all other databases
         * The file extension should be specified without a leading .
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The DB dump is already gzip-compressed — use STORE (no extra compression)
             * to avoid CPU waste and zip-within-gzip overhead.
             */
            'compression_method' => ZipArchive::CM_STORE,

            'compression_level' => 0,

            'filename_prefix' => 'ogami-erp-',

            'disks' => [
                'local',
            ],
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * You can set it to `null` or `false` to disable encryption.
         *
         * When set to 'default', we'll use ZipArchive::EM_AES_256 if it is
         * available on your system.
         */
        'encryption' => 'default',

        /*
         * The number of attempts, in case the backup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     *
     * You can also use your own notification classes, just make sure the class is named after one of
     * the `Spatie\Backup\Notifications\Notifications` classes.
     */
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFY_EMAIL', env('MAIL_FROM_ADDRESS', 'erp@ogamimfg.local')),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'erp@ogamimfg.local'),
                'name' => env('MAIL_FROM_NAME', 'Ogami ERP'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',

            /*
             * If this is an empty string, the name field on the webhook will be used.
             */
            'username' => '',

            /*
             * If this is an empty string, the avatar on the webhook will be used.
             */
            'avatar_url' => '',
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],

        /*
        [
            'name' => 'name of the second app',
            'disks' => ['local', 's3'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
        */
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will
         * be kept and so on.
         *
         * No matter how you configure it the default strategy will never
         * delete the newest backup.
         */
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            // Keep every backup for the first 14 days
            'keep_all_backups_for_days' => 14,

            // After that, keep one per day for 30 days (≈ 1 calendar month)
            'keep_daily_backups_for_days' => 30,

            // After that, keep one per week for 12 weeks (≈ 3 months)
            'keep_weekly_backups_for_weeks' => 12,

            // After that, keep one per month for 12 months
            'keep_monthly_backups_for_months' => 12,

            // After that, keep one per year for 5 years
            'keep_yearly_backups_for_years' => 5,

            // Hard cap: delete oldest backups when total storage exceeds 2 GB
            'delete_oldest_backups_when_using_more_megabytes_than' => 2048,
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

];
