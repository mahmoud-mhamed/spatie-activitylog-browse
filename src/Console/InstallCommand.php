<?php

namespace Mhamed\SpatieActivitylogBrowse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'activitylog-browse:install';

    protected $description = 'Install the ActivityLog Browse package (publishes spatie migration + config)';

    public function handle(): int
    {
        $this->info('Installing ActivityLog Browse...');

        // Publish spatie/laravel-activitylog migration
        $this->info('Publishing spatie/laravel-activitylog migration...');
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\Activitylog\ActivitylogServiceProvider',
            '--tag' => 'activitylog-migrations',
        ]);

        // Publish our config
        $this->info('Publishing activitylog-browse config...');
        $this->call('vendor:publish', [
            '--tag' => 'activitylog-browse-config',
        ]);

        // Run migrations
        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        // Add performance indexes
        if ($this->confirm('Add performance indexes to activity_log table?', true)) {
            $this->addIndexes();
        }

        $this->info('ActivityLog Browse installed successfully.');
        $this->info('Visit /' . config('activitylog-browse.browse.prefix', 'activity-log') . ' to browse your logs.');

        return self::SUCCESS;
    }

    protected function addIndexes(): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        if (! Schema::hasTable($table)) {
            $this->warn("Table '{$table}' not found. Skipping indexes.");
            return;
        }

        $indexes = [
            'activity_log_subject_type_subject_id_index' => ['subject_type', 'subject_id'],
            'activity_log_causer_type_causer_id_index' => ['causer_type', 'causer_id'],
            'activity_log_log_name_index' => ['log_name'],
            'activity_log_event_index' => ['event'],
        ];

        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $existing = collect($sm->listTableIndexes($table))->keys()->map(fn ($k) => strtolower($k));

        foreach ($indexes as $name => $columns) {
            if ($existing->contains(strtolower($name))) {
                $this->line("  Index '{$name}' already exists. Skipping.");
                continue;
            }

            Schema::table($table, function ($t) use ($columns, $name) {
                $t->index($columns, $name);
            });
            $this->info("  Added index '{$name}'.");
        }
    }
}
