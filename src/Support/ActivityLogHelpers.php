<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\ActivitylogServiceProvider;

class ActivityLogHelpers
{
    public static function cachePrefix(): string
    {
        if (function_exists('tenant') && tenant()) {
            return 'activitylog-browse:t:' . tenant()->getTenantKey();
        }

        return 'activitylog-browse';
    }

    public static function activityConnection(): string
    {
        $model = ActivitylogServiceProvider::determineActivityModel();

        return (new $model)->getConnectionName() ?? config('database.default');
    }

    public static function tableName(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    public static function clearStatsCache(bool $optimize = true): void
    {
        $prefix = self::cachePrefix();

        $sections = ['overview', 'events', 'log_names', 'models', 'causers', 'daily', 'hourly', 'weekday', 'system_user', 'attributes', 'monthly', 'peak_day'];

        foreach ($sections as $section) {
            Cache::forget("{$prefix}:stats:{$section}");
        }

        $filterColumns = ['log_name', 'event', 'subject_type', 'causer_type'];
        foreach ($filterColumns as $column) {
            Cache::forget("{$prefix}:{$column}");
        }

        if ($optimize) {
            self::optimizeTable();
        }
    }

    public static function optimizeTable(): void
    {
        try {
            DB::connection(self::activityConnection())
                ->statement('OPTIMIZE TABLE `' . self::tableName() . '`');
        } catch (\Throwable) {
        }
    }

    public static function tableSizeBytes(): ?int
    {
        try {
            $conn = DB::connection(self::activityConnection());
            $table = self::tableName();
            $conn->statement("ANALYZE TABLE `{$table}`");
            $result = $conn->selectOne(
                'SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$conn->getDatabaseName(), $table]
            );

            return $result?->size ? (int) $result->size : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
