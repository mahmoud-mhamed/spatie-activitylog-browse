<?php

namespace Mhamed\SpatieActivitylogBrowse\Observers;

use Spatie\Activitylog\Models\Activity;
use Mhamed\SpatieActivitylogBrowse\Helpers\DeviceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;

class ActivityEnrichmentObserver
{
    public function creating(Activity $activity): void
    {
        $enrichment = array_merge(
            RequestDataCollector::collect(),
            DeviceDataCollector::collect(),
        );

        if (empty($enrichment)) {
            return;
        }

        $properties = $activity->properties?->toArray() ?? [];

        $activity->properties = collect(array_merge($properties, $enrichment));
    }
}
