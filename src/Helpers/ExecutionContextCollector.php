<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

class ExecutionContextCollector
{
    public static function collect(): array
    {
        $config = config('activitylog-browse.execution_context');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['source'] ?? false) {
            $data['source'] = static::detectSource();
        }

        if ($fields['job_name'] ?? false) {
            $jobName = static::detectJobName();

            if ($jobName) {
                $data['job_name'] = $jobName;
            }
        }

        if ($fields['command_name'] ?? false) {
            if (app()->runningInConsole() && isset($_SERVER['argv'][1])) {
                $data['command_name'] = $_SERVER['argv'][1];
            }
        }

        return $data ? ['execution_context' => $data] : [];
    }

    protected static function detectSource(): string
    {
        if (app()->runningInConsole()) {
            if (static::detectJobName()) {
                return 'queue';
            }

            if (class_exists(\Illuminate\Console\Scheduling\Schedule::class) && app()->bound(\Illuminate\Console\Scheduling\Schedule::class)) {
                return 'schedule';
            }

            return 'console';
        }

        return 'web';
    }

    protected static function detectJobName(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $frame) {
            if (isset($frame['class']) && is_a($frame['class'], \Illuminate\Contracts\Queue\Job::class, true) && ($frame['function'] ?? '') === 'fire') {
                return $frame['class'];
            }

            if (isset($frame['class']) && is_a($frame['class'], \Illuminate\Contracts\Queue\ShouldQueue::class, true) && ($frame['function'] ?? '') === 'handle') {
                return $frame['class'];
            }
        }

        return null;
    }
}
