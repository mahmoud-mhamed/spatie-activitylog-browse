<?php

namespace Mhamed\SpatieActivitylogBrowse\Observers;

use Spatie\Activitylog\Models\Activity;
use Mhamed\SpatieActivitylogBrowse\Helpers\AppDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\DeviceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\ExecutionContextCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\PerformanceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\SessionDataCollector;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;

class ActivityEnrichmentObserver
{
    /** @var array<int, callable():array>|null */
    private static ?array $collectors = null;

    /**
     * Enrichment is best-effort: this runs on every activity insert, i.e. inside the
     * host app's model saves. Any failure here is reported and skipped, never thrown.
     */
    public function creating(Activity $activity): void
    {
        try {
            $requestId = self::assignRequestId($activity);
        } catch (\Throwable $e) {
            report($e);
            $requestId = null;
        }

        $enrichment = [];
        foreach (self::collectors() as $collector) {
            try {
                $result = $collector();
                if (! empty($result)) {
                    $enrichment += $result;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (empty($enrichment)) {
            return;
        }

        try {
            // Every activity of a request carries the same body. Once request_id links
            // them, keep it on the first one that is actually saved (claimed in created());
            // the UI looks it up from there. A transaction rollback releases the claim.
            if ($requestId !== null && isset($enrichment['request_data']['body']) && RequestDataCollector::isBodyClaimed()) {
                unset($enrichment['request_data']['body']);
            }

            $properties = $activity->properties?->toArray() ?? [];
            $activity->properties = collect(array_merge($properties, $enrichment));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The body is claimed only once its activity is saved, so a cancelled insert doesn't lose it. */
    public function created(Activity $activity): void
    {
        try {
            if (($activity->getAttributes()['request_id'] ?? null) !== null && isset($activity->properties['request_data']['body'])) {
                RequestDataCollector::claimBody();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Stamp the request/job group id. requestId() is checked first: it is null
     * outside HTTP requests and jobs, which spares the scheduler the column lookup.
     */
    private static function assignRequestId(Activity $activity): ?string
    {
        $requestId = RequestDataCollector::requestId();
        if ($requestId === null || ! ActivityLogHelpers::hasRequestIdColumn()) {
            return null;
        }

        $activity->request_id ??= $requestId;

        return $requestId;
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
