<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

class PerformanceDataCollector
{
    /**
     * Measurement baselines of the queue jobs running in this process, keyed by job
     * object id. A long-lived worker would otherwise report time, queries and memory
     * since the worker started (hours, tens of thousands of queries) for every job.
     *
     * @var array<int, array{started_at: float, queries: int}>
     */
    private static array $jobScopes = [];

    public static function beginJob(object $job): void
    {
        // Peak memory can only be reset process-wide, so only for the outermost job.
        if (self::$jobScopes === [] && function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        self::$jobScopes[spl_object_id($job)] = ['started_at' => microtime(true), 'queries' => QueryCounter::count()];
    }

    /** Safe to call more than once per job (a failing job fires several events). */
    public static function endJob(object $job): void
    {
        unset(self::$jobScopes[spl_object_id($job)]);
    }

    public static function collect(): array
    {
        if (! RuntimeContext::isWebContext()) {
            return [];
        }

        $config = config('activitylog-browse.performance_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        $jobScope = self::$jobScopes ? end(self::$jobScopes) : null;

        if ($fields['request_duration'] ?? false) {
            $startedAt = $jobScope['started_at'] ?? (defined('LARAVEL_START') ? LARAVEL_START : null);
            if ($startedAt !== null) {
                $data['request_duration'] = round((microtime(true) - $startedAt) * 1000, 2);
            }
        }

        if ($fields['memory_peak'] ?? false) {
            $data['memory_peak'] = memory_get_peak_usage(true);
        }

        if ($fields['db_query_count'] ?? false) {
            $data['db_query_count'] = QueryCounter::count() - ($jobScope['queries'] ?? 0);
        }

        return $data ? ['performance_data' => $data] : [];
    }
}
