<?php

namespace Mhamed\SpatieActivitylogBrowse\Console;

use Illuminate\Console\Command;

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

        $this->info('ActivityLog Browse installed successfully.');
        $this->info('Visit /' . config('activitylog-browse.browse.prefix', 'activity-log') . ' to browse your logs.');

        return self::SUCCESS;
    }
}
