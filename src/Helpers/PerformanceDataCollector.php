<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

class PerformanceDataCollector
{
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

        if ($fields['request_duration'] ?? false) {
            if (defined('LARAVEL_START')) {
                $data['request_duration'] = round((microtime(true) - LARAVEL_START) * 1000, 2);
            }
        }

        if ($fields['memory_peak'] ?? false) {
            $data['memory_peak'] = memory_get_peak_usage(true);
        }

        if ($fields['db_query_count'] ?? false) {
            $data['db_query_count'] = QueryCounter::count();
        }

        return $data ? ['performance_data' => $data] : [];
    }
}
