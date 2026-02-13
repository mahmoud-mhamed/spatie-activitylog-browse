<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class PerformanceDataCollector
{
    public static function collect(): array
    {
        if (app()->runningInConsole() && ! Request::instance()->getHost()) {
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
            try {
                $data['db_query_count'] = count(DB::getQueryLog());
            } catch (\Throwable) {
                // Query log may not be enabled
            }
        }

        return $data ? ['performance_data' => $data] : [];
    }
}
