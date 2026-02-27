<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Migrations
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically load its migrations.
    | Set to false if you want to publish and manage migrations yourself,
    | for example when using multi-database tenancy (stancl/tenancy).
    |
    | You can publish migrations with:
    | php artisan vendor:publish --tag=activitylog-browse-migrations
    |
    */

    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Log All Models
    |--------------------------------------------------------------------------
    |
    | When enabled, all Eloquent model events (created, updated, deleted) will
    | be automatically logged without requiring the LogsActivity trait.
    | Models that already use LogsActivity are skipped to prevent duplicates.
    |
    */

    'auto_log' => [
        'enabled' => true,

        'events' => ['created', 'updated', 'deleted'],

        // Which models to log. Use '*' for all models, or an array of specific classes:
        // 'models' => [App\Models\User::class, App\Models\Order::class],
        'models' => '*',

        'excluded_models' => [
            // App\Models\TemporaryFile::class,
        ],

        'log_name' => 'default',

        'log_only_dirty' => true,

        'excluded_attributes' => [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ],

        'submit_empty_logs' => false,

        // When enabled, null values will be stripped from attributes on 'created' events.
        'exclude_null_on_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Data Enrichment
    |--------------------------------------------------------------------------
    |
    | When enabled, request context (URL, method, etc.) is automatically
    | attached to every activity log entry.
    |
    */

    'request_data' => [
        'enabled' => true,

        'fields' => [
            'url' => true,
            'previous_url' => true,
            'method' => true,
            'route_name' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Data Enrichment
    |--------------------------------------------------------------------------
    |
    | When enabled, device/client information (IP, user agent, referrer) is
    | automatically attached to every activity log entry.
    |
    */

    'device_data' => [
        'enabled' => true,

        'fields' => [
            'ip' => true,
            'user_agent' => true,
            'referrer' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Data Enrichment
    |--------------------------------------------------------------------------
    |
    | When enabled, performance metrics (duration, memory, query count) are
    | automatically attached to every activity log entry.
    |
    */

    'performance_data' => [
        'enabled' => true,

        'fields' => [
            'request_duration' => true,
            'memory_peak' => true,
            'db_query_count' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | App Data Enrichment
    |--------------------------------------------------------------------------
    |
    | When enabled, application context (environment, versions, hostname) is
    | automatically attached to every activity log entry.
    |
    */

    'app_data' => [
        'enabled' => true,

        'fields' => [
            'environment' => true,
            'php_version' => true,
            'server_hostname' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Data Enrichment
    |--------------------------------------------------------------------------
    |
    | When enabled, session context (session ID, auth guard) is automatically
    | attached to every activity log entry. Only available in web context.
    |
    */

    'session_data' => [
        'enabled' => true,

        'fields' => [
            'auth_guard' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Context Enrichment
    |--------------------------------------------------------------------------
    |
    | When enabled, execution context (source, job name, command name) is
    | automatically attached to every activity log entry.
    |
    */

    'execution_context' => [
        'enabled' => true,

        'fields' => [
            'source' => true,
            'job_name' => true,
            'command_name' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browse UI
    |--------------------------------------------------------------------------
    |
    | Configuration for the web-based activity log browser.
    |
    */

    'browse' => [
        'enabled' => true,

        'prefix' => 'activity-log',

        'middleware' => ['web', 'auth'],

        'per_page' => 25,

        // Gate name to authorize access. Set to null to allow all authenticated users.
        'gate' => null,

        // Available locales for the language switch button.
        'available_locales' => ['en', 'ar'],
    ],

];
