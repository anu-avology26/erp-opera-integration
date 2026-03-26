<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data types supported for sync (extensible)
    |--------------------------------------------------------------------------
    */
    'data_types' => [
        'ar_accounts' => [
            'label' => 'AR Accounts',
            'command' => 'sync:erp-opera-accounts',
        ],
        'reservations' => [
            'label' => 'Reservations',
            'command' => null,
        ],
        'guest_profiles' => [
            'label' => 'Guest Profiles',
            'command' => null,
        ],
        'rates' => [
            'label' => 'Rates',
            'command' => null,
        ],
        'inventory' => [
            'label' => 'Inventory',
            'command' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedules per data type and optionally per property (hotel)
    | Format: [ 'data_type' => 'ar_accounts', 'property_id' => null, 'time' => '02:00' ]
    | property_id null = all properties. time = HH:MM 24h.
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Failure notifications: log, mail, slack (comma-separated or array)
    |--------------------------------------------------------------------------
    */
    'notify_on_failure' => array_filter(array_map('trim', explode(',', env('INTEGRATION_NOTIFY_ON_FAILURE', 'log')))),

    'notification_email' => env('INTEGRATION_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),

    /*
    |--------------------------------------------------------------------------
    | Payload audit (encrypted, limited retention – for troubleshooting only)
    | Full raw payloads are never logged in plain text; metadata (IDs, status) is logged.
    |--------------------------------------------------------------------------
    */
    'payload_audit_enabled' => (bool) env('PAYLOAD_AUDIT_ENABLED', false),
    'payload_audit_retention_days' => (int) env('PAYLOAD_AUDIT_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Sync logging limits
    |--------------------------------------------------------------------------
    | Limit number of error entries stored in SyncLog->errors to keep payloads small.
    */
    'sync_error_log_limit' => (int) env('SYNC_ERROR_LOG_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | Caching policy
    | OAuth tokens and configuration metadata are cached. Business data
    | (customers, financials) is not cached long-term to avoid inconsistencies.
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Data source adapters: which adapter handles which data type
    |--------------------------------------------------------------------------
    */
    'data_source_adapter' => env('INTEGRATION_DATA_SOURCE_ADAPTER', 'erp_rest_api'),

    'csv_data_types' => array_filter(explode(',', env('INTEGRATION_CSV_DATA_TYPES', 'reservations,guest_profiles,rates,inventory'))),

    'db_table_map' => [
        'ar_accounts' => 'erp_customers',
        'reservations' => 'reservations',
        'guest_profiles' => 'guest_profiles',
        'rates' => 'rates',
        'inventory' => 'inventory',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload sync chunk size
    |--------------------------------------------------------------------------
    */
    'upload_chunk_size' => (int) env('UPLOAD_CHUNK_SIZE', 200),

    'schedules' => array_filter(array_merge(
        [
            [
                'data_type' => 'ar_accounts',
                'property_id' => null,
                'time' => sprintf('%02d:%02d', (int) env('SYNC_BATCH_WINDOW_HOUR', 12), (int) env('SYNC_BATCH_WINDOW_MINUTE', 0)),
            ],
        ],
        config('integration.schedules_extra', [])
    ), fn ($s) => ! empty($s['data_type'])),

];
