<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

class ExecutionContextCollector
{
    private static ?string $cachedSource = null;
    private static ?string $cachedJobName = null;
    private static bool $jobNameDetected = false;
    private static ?string $cachedCommandName = null;
    private static bool $commandNameDetected = false;

    public static function collect(): array
    {
        $config = config('activitylog-browse.execution_context');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['source'] ?? false) {
            $data['source'] = self::source();
        }

        if ($fields['job_name'] ?? false) {
            $jobName = self::jobName();

            if ($jobName) {
                $data['job_name'] = $jobName;
            }
        }

        if ($fields['command_name'] ?? false) {
            $cmd = self::commandName();
            if ($cmd) {
                $data['command_name'] = $cmd;
            }
        }

        return $data ? ['execution_context' => $data] : [];
    }

    public static function resetCache(): void
    {
        self::$cachedSource = null;
        self::$cachedJobName = null;
        self::$jobNameDetected = false;
        self::$cachedCommandName = null;
        self::$commandNameDetected = false;
    }

    protected static function source(): string
    {
        if (self::$cachedSource !== null) {
            return self::$cachedSource;
        }

        if (! app()->runningInConsole()) {
            return self::$cachedSource = 'web';
        }

        if (self::jobName()) {
            return self::$cachedSource = 'queue';
        }

        if (class_exists(\Illuminate\Console\Scheduling\Schedule::class)
            && app()->bound(\Illuminate\Console\Scheduling\Schedule::class)) {
            return self::$cachedSource = 'schedule';
        }

        return self::$cachedSource = 'console';
    }

    protected static function jobName(): ?string
    {
        if (self::$jobNameDetected) {
            return self::$cachedJobName;
        }

        self::$jobNameDetected = true;

        if (! app()->runningInConsole()) {
            return self::$cachedJobName = null;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $frame) {
            if (isset($frame['class']) && is_a($frame['class'], \Illuminate\Contracts\Queue\Job::class, true) && ($frame['function'] ?? '') === 'fire') {
                return self::$cachedJobName = $frame['class'];
            }

            if (isset($frame['class']) && is_a($frame['class'], \Illuminate\Contracts\Queue\ShouldQueue::class, true) && ($frame['function'] ?? '') === 'handle') {
                return self::$cachedJobName = $frame['class'];
            }
        }

        return self::$cachedJobName = null;
    }

    protected static function commandName(): ?string
    {
        if (self::$commandNameDetected) {
            return self::$cachedCommandName;
        }

        self::$commandNameDetected = true;

        if (app()->runningInConsole() && isset($_SERVER['argv'][1])) {
            return self::$cachedCommandName = $_SERVER['argv'][1];
        }

        return self::$cachedCommandName = null;
    }
}
