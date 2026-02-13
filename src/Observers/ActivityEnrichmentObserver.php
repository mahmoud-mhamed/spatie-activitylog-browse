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
    public function creating(Activity $activity): void
    {
        $enrichment = array_merge(
            RequestDataCollector::collect(),
            DeviceDataCollector::collect(),
            PerformanceDataCollector::collect(),
            AppDataCollector::collect(),
            SessionDataCollector::collect(),
            ExecutionContextCollector::collect(),
        );

        if (empty($enrichment)) {
            return;
        }

        $properties = $activity->properties?->toArray() ?? [];

        $activity->properties = collect(array_merge($properties, $enrichment));
    }
}
