<?php

namespace Mhamed\SpatieActivitylogBrowse\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Traits\LogsActivity;

class GlobalModelLogger
{
    protected array $oldAttributes = [];

    protected bool $isLogging = false;

    public function register(): void
    {
        $events = config('activitylog-browse.auto_log.events', ['created', 'updated', 'deleted']);

        if (in_array('updated', $events)) {
            Event::listen('eloquent.updating: *', function (string $eventName, array $data) {
                $model = $data[0];

                if ($this->shouldLog($model)) {
                    $this->oldAttributes[$this->modelKey($model)] = $model->getRawOriginal();
                }
            });
        }

        foreach ($events as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $data) use ($event) {
                $model = $data[0];

                if ($this->isLogging) {
                    return;
                }

                if ($this->shouldLog($model)) {
                    $this->logActivity($model, $event);
                }
            });
        }
    }

    protected function shouldLog(Model $model): bool
    {
        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        if ($model instanceof $activityModel) {
            return false;
        }

        if (in_array(LogsActivity::class, class_uses_recursive($model))) {
            return false;
        }

        $models = config('activitylog-browse.auto_log.models', '*');

        if ($models !== '*') {
            if (! in_array(get_class($model), (array) $models)) {
                return false;
            }
        }

        $excluded = config('activitylog-browse.auto_log.excluded_models', []);

        if (in_array(get_class($model), $excluded)) {
            return false;
        }

        return true;
    }

    protected function logActivity(Model $model, string $event): void
    {
        $config = config('activitylog-browse.auto_log');
        $excludedAttributes = $config['excluded_attributes'] ?? [];
        $logOnlyDirty = $config['log_only_dirty'] ?? true;

        $properties = [];

        if ($event === 'updated') {
            $old = $this->oldAttributes[$this->modelKey($model)] ?? [];
            unset($this->oldAttributes[$this->modelKey($model)]);

            $changed = $model->getChanges();
            $changed = array_diff_key($changed, array_flip($excludedAttributes));
            $old = array_intersect_key($old, $changed);
            $old = array_diff_key($old, array_flip($excludedAttributes));

            if ($logOnlyDirty && empty($changed)) {
                return;
            }

            if (empty($changed) && ! ($config['submit_empty_logs'] ?? false)) {
                return;
            }

            $properties['old'] = $old;
            $properties['attributes'] = $changed;
        } elseif ($event === 'created') {
            $attributes = array_diff_key($model->getAttributes(), array_flip($excludedAttributes));
            $properties['attributes'] = $attributes;
        } elseif ($event === 'deleted') {
            $attributes = array_diff_key($model->getAttributes(), array_flip($excludedAttributes));
            $properties['old'] = $attributes;
        }

        $logName = $config['log_name'] ?? 'default';
        $modelClass = class_basename($model);
        $description = "{$event} {$modelClass}";

        $this->isLogging = true;

        try {
            activity($logName)
                ->event($event)
                ->performedOn($model)
                ->withProperties($properties)
                ->log($description);
        } catch (\Throwable $e) {
            if ($this->isDataTruncationError($e)) {
                try {
                    ColumnMigrator::fixMorphIdColumns();

                    activity($logName)
                        ->event($event)
                        ->performedOn($model)
                        ->withProperties($properties)
                        ->log($description);
                } catch (\Throwable $retryException) {
                    report($retryException);
                }
            } else {
                report($e);
            }
        } finally {
            $this->isLogging = false;
        }
    }

    protected function isDataTruncationError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Data truncated')
            || str_contains($message, '1265')
            || str_contains($message, 'Numeric value out of range');
    }

    protected function modelKey(Model $model): string
    {
        return get_class($model) . ':' . $model->getKey();
    }
}
