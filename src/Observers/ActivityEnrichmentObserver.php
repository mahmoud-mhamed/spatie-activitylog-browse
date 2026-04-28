<?php

namespace Mhamed\SpatieActivitylogBrowse\Observers;

use Spatie\Activitylog\Models\Activity;
use Mhamed\SpatieActivitylogBrowse\Helpers\AppDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\DeviceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\ExecutionContextCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\PerformanceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\SessionDataCollector;

class ActivityEnrichmentObserver
{
    /** @var array<int, callable():array>|null */
    private static ?array $collectors = null;

    public function creating(Activity $activity): void
    {
        $collectors = self::collectors();
        if (empty($collectors)) {
            return;
        }

        $enrichment = [];
        foreach ($collectors as $collector) {
            $result = $collector();
            if (! empty($result)) {
                $enrichment += $result;
            }
        }

        if (empty($enrichment)) {
            return;
        }

        $properties = $activity->properties?->toArray() ?? [];

        $activity->properties = collect(array_merge($properties, $enrichment));
    }

    /**
     * Build the list of enabled collectors once. Cheap config lookups happen
     * here at first call; subsequent log entries skip disabled collectors
     * entirely instead of calling them and bailing inside.
     *
     * @return array<int, callable():array>
     */
    private static function collectors(): array
    {
        if (self::$collectors !== null) {
            return self::$collectors;
        }

        $cfg = config('activitylog-browse');
        $list = [];

        if ($cfg['request_data']['enabled'] ?? false) {
            $list[] = [RequestDataCollector::class, 'collect'];
        }
        if ($cfg['device_data']['enabled'] ?? false) {
            $list[] = [DeviceDataCollector::class, 'collect'];
        }
        if ($cfg['performance_data']['enabled'] ?? false) {
            $list[] = [PerformanceDataCollector::class, 'collect'];
        }
        if ($cfg['app_data']['enabled'] ?? false) {
            $list[] = [AppDataCollector::class, 'collect'];
        }
        if ($cfg['session_data']['enabled'] ?? false) {
            $list[] = [SessionDataCollector::class, 'collect'];
        }
        if ($cfg['execution_context']['enabled'] ?? false) {
            $list[] = [ExecutionContextCollector::class, 'collect'];
        }

        return self::$collectors = $list;
    }

    public static function resetCache(): void
    {
        self::$collectors = null;
    }
}
