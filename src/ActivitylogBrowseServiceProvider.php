<?php

namespace Mhamed\SpatieActivitylogBrowse;

use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Mhamed\SpatieActivitylogBrowse\Console\InstallCommand;
use Mhamed\SpatieActivitylogBrowse\Listeners\GlobalModelLogger;
use Mhamed\SpatieActivitylogBrowse\Observers\ActivityEnrichmentObserver;

class ActivitylogBrowseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/activitylog-browse.php', 'activitylog-browse');

        $this->app->singleton(GlobalModelLogger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishAssets();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'activitylog-browse');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'activitylog-browse');

        if (config('activitylog-browse.auto_log.enabled')) {
            $this->registerGlobalModelLogger();
        }

        if ($this->isEnrichmentEnabled()) {
            $this->registerEnrichmentObserver();
        }

        if (config('activitylog-browse.browse.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }

    protected function publishAssets(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
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
