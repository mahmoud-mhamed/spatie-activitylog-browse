<?php

namespace Mhamed\SpatieActivitylogBrowse;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Mhamed\SpatieActivitylogBrowse\Console\EnsureColumnsCommand;
use Mhamed\SpatieActivitylogBrowse\Console\InstallCommand;
use Mhamed\SpatieActivitylogBrowse\Console\PruneCommand;
use Mhamed\SpatieActivitylogBrowse\Helpers\ExecutionContextCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\PerformanceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\QueryCounter;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Listeners\GlobalModelLogger;
use Mhamed\SpatieActivitylogBrowse\Observers\ActivityEnrichmentObserver;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;

class ActivitylogBrowseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/activitylog-browse.php', 'activitylog-browse');

        $this->app->singleton(GlobalModelLogger::class);
    }

    public function boot(): void
    {
        if (config('activitylog-browse.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
        $this->publishAssets();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'activitylog-browse');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'activitylog-browse');

        if (config('activitylog-browse.auto_log.enabled')) {
            $this->registerGlobalModelLogger();
        }

        if ($this->isEnrichmentEnabled()) {
            $this->registerEnrichmentObserver();
        }

        if (config('activitylog-browse.request_data.fields.body')) {
            Event::listen(TransactionRolledBack::class, fn () => RequestDataCollector::releaseBody());
        }

        $this->registerJobRequestIdPropagation();
        $this->registerExecutionScopes();

        if (config('activitylog-browse.performance_data.enabled')
            && (config('activitylog-browse.performance_data.fields.db_query_count') ?? false)) {
            QueryCounter::register();
        }

        if (config('activitylog-browse.browse.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        $this->registerRetentionSchedule();
        $this->registerColumnRetrySchedule();
        $this->registerBodyRetentionSchedule();
        $this->registerOptimizeSchedule();
    }

    /**
     * Track which queue job / scheduled task is running, so a long-lived worker or
     * schedule:run process attributes each activity to the right job: its group id
     * (inherited from the dispatching request), job-relative performance figures,
     * and the job class + source in the execution context.
     */
    protected function registerExecutionScopes(): void
    {
        $requestIds = config('activitylog-browse.request_data.enabled') && (config('activitylog-browse.request_data.fields.request_id') ?? true);
        $performance = (bool) config('activitylog-browse.performance_data.enabled');
        $execution = (bool) config('activitylog-browse.execution_context.enabled');

        if (! $requestIds && ! $performance && ! $execution) {
            return;
        }

        Event::listen(JobProcessing::class, function (JobProcessing $event) use ($requestIds, $performance, $execution) {
            $job = $event->job;
            if ($requestIds) {
                $payload = $job->payload();
                $inherited = $payload[RequestDataCollector::JOB_PAYLOAD_KEY] ?? null;
                RequestDataCollector::beginJob($job, is_string($inherited) ? $inherited : null);
            }
            if ($performance) {
                PerformanceDataCollector::beginJob($job);
            }
            if ($execution) {
                ExecutionContextCollector::beginJob($job, $job->resolveName());
            }
        });

        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $finished) {
            Event::listen($finished, function ($event) {
                RequestDataCollector::endJob($event->job);
                PerformanceDataCollector::endJob($event->job);
                ExecutionContextCollector::endJob($event->job);
            });
        }

        if ($execution) {
            Event::listen(ScheduledTaskStarting::class, fn () => ExecutionContextCollector::beginScheduledTask());
            // (ScheduledTaskSkipped is fired instead of Starting, so it has no matching end.)
            foreach ([ScheduledTaskFinished::class, ScheduledTaskFailed::class] as $finished) {
                Event::listen($finished, fn () => ExecutionContextCollector::endScheduledTask());
            }
        }
    }

    /**
     * Rebuild the table at 04:00 when a cleanup freed space (after the 03:00 retention and
     * 03:30 body runs). Follows retention.optimize_after, like the retention prune does.
     */
    protected function registerOptimizeSchedule(): void
    {
        if (! $this->app->runningInConsole() || ! config('activitylog-browse.retention.optimize_after', true)) {
            return;
        }

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('activitylog-browse:prune --optimize')
                ->dailyAt('04:00')
                ->when(fn () => ActivityLogHelpers::optimizePendingSince() !== null)
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    /** Strip old request bodies nightly when request_data.body.retention is enabled. */
    protected function registerBodyRetentionSchedule(): void
    {
        if (! $this->app->runningInConsole() || ! (config('activitylog-browse.request_data.body.retention.enabled') ?? false)) {
            return;
        }

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('activitylog-browse:prune --bodies')
                ->dailyAt('03:30')
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    /**
     * Jobs inherit the group id of the request (or job) that dispatched them, so
     * "all changes in this request" includes the work its queued jobs did later.
     * Automatic: the id travels in the job payload; nothing to change in the app.
     */
    protected function registerJobRequestIdPropagation(): void
    {
        if (! config('activitylog-browse.request_data.enabled') || ! (config('activitylog-browse.request_data.fields.request_id') ?? true)) {
            return;
        }

        Queue::createPayloadUsing(function () {
            try {
                return RequestDataCollector::payloadForDispatch();
            } catch (\Throwable $e) {
                // Never block a dispatch over activity grouping.
                report($e);

                return [];
            }
        });

    }

    /**
     * Retry adding the request_id column nightly while it is missing (the
     * migration gives up quietly when the table is busy). 03:00 keeps the
     * short ALTER lock wait away from peak traffic.
     */
    protected function registerColumnRetrySchedule(): void
    {
        if (! $this->app->runningInConsole() || ! (config('activitylog-browse.request_data.fields.request_id') ?? true)) {
            return;
        }

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('activitylog-browse:ensure-columns')
                ->dailyAt('03:00')
                ->when(fn () => ! ActivityLogHelpers::hasRequestIdColumn())
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    protected function registerRetentionSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! config('activitylog-browse.retention.enabled', false)) {
            return;
        }

        $frequency = config('activitylog-browse.retention.schedule');
        if (! $frequency) {
            return;
        }

        $time = (string) config('activitylog-browse.retention.schedule_time', '03:00');
        if (! preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time = '03:00';
        }

        $this->app->booted(function () use ($frequency, $time) {
            $schedule = $this->app->make(Schedule::class);
            $event = $schedule->command('activitylog-browse:prune');

            match ($frequency) {
                'daily'   => $event->dailyAt($time),
                'weekly'  => $event->weeklyOn(0, $time),
                'monthly' => $event->monthlyOn(1, $time),
                default   => null,
            };

            $event->withoutOverlapping()->runInBackground();
        });
    }

    protected function publishAssets(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnsureColumnsCommand::class,
                InstallCommand::class,
                PruneCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/activitylog-browse.php' => config_path('activitylog-browse.php'),
            ], 'activitylog-browse-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/activitylog-browse'),
            ], 'activitylog-browse-views');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/activitylog-browse'),
            ], 'activitylog-browse-lang');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'activitylog-browse-migrations');
        }
    }

    protected function registerGlobalModelLogger(): void
    {
        $logger = $this->app->make(GlobalModelLogger::class);
        $logger->register();
    }

    protected function registerEnrichmentObserver(): void
    {
        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activityModel::observe(ActivityEnrichmentObserver::class);
    }

    protected function isEnrichmentEnabled(): bool
    {
        return config('activitylog-browse.request_data.enabled', false)
            || config('activitylog-browse.device_data.enabled', false)
            || config('activitylog-browse.performance_data.enabled', false)
            || config('activitylog-browse.app_data.enabled', false)
            || config('activitylog-browse.session_data.enabled', false)
            || config('activitylog-browse.execution_context.enabled', false);
    }
}
