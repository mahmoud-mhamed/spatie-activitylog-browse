<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

class ExecutionContextCollector
{
    /**
     * Class names of the queue jobs running in this process, keyed by job object id
     * (a stack: a job can run another one synchronously). Fed by the queue events,
     * so a long-lived worker reports the job that is actually running.
     *
     * @var array<int, string>
     */
    private static array $jobNames = [];

    /** Scheduled tasks running inside this process (closures / jobs run by schedule:run). */
    private static int $scheduledTasks = 0;

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

    public static function beginJob(object $job, string $name): void
    {
        self::$jobNames[spl_object_id($job)] = $name;
    }

    /** Safe to call more than once per job (a failing job fires several events). */
    public static function endJob(object $job): void
    {
        unset(self::$jobNames[spl_object_id($job)]);
    }

    public static function beginScheduledTask(): void
    {
        self::$scheduledTasks++;
    }

    public static function endScheduledTask(): void
    {
        self::$scheduledTasks = max(0, self::$scheduledTasks - 1);
    }

    public static function resetCache(): void
    {
        self::$jobNames = [];
        self::$scheduledTasks = 0;
        self::$cachedCommandName = null;
        self::$commandNameDetected = false;
    }

    /**
     * Worked out per activity: one process (a queue worker, schedule:run) runs many
     * different things. "schedule" only for work done by the scheduler process itself;
     * a command the scheduler spawns runs in its own process and reports "console".
     */
    protected static function source(): string
    {
        if (RuntimeContext::isHttpRequest()) {
            return 'web';
        }

        if (self::$jobNames) {
            return 'queue';
        }

        if (self::$scheduledTasks > 0 || in_array(self::commandName(), ['schedule:run', 'schedule:work', 'schedule:test'], true)) {
            return 'schedule';
        }

        return 'console';
    }

    protected static function jobName(): ?string
    {
        return self::$jobNames ? end(self::$jobNames) : null;
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
