<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Request;

/**
 * Per-process cache for runtime context checks shared between collectors.
 *
 * `app()->runningInConsole()` and `Request::instance()->getHost()` together
 * cost ~5–10µs per call. They are invariant for the lifetime of the process,
 * so we resolve them once and reuse the result.
 */
class RuntimeContext
{
    private static ?bool $isWebContext = null;
    private static ?bool $isConsole = null;
    private static ?bool $isOctane = null;
    private static ?string $hostname = null;

    /**
     * True when there is a real HTTP request in flight (or an artisan command
     * dispatched via `serve`/Octane that still has a request bound).
     */
    public static function isWebContext(): bool
    {
        if (self::$isWebContext !== null) {
            return self::$isWebContext;
        }

        if (! self::isConsole()) {
            return self::$isWebContext = true;
        }

        return self::$isWebContext = (bool) Request::instance()->getHost();
    }

    public static function isConsole(): bool
    {
        return self::$isConsole ??= app()->runningInConsole();
    }

    /**
     * True only for a real HTTP request (php-fpm, `artisan serve`, or an Octane worker).
     * Unlike isWebContext(), this is false for queue jobs, scheduled tasks and artisan
     * commands: Laravel gives them a placeholder Request built from APP_URL (GET,
     * 127.0.0.1, "Symfony" user agent), so request/device data recorded there is fake.
     */
    public static function isHttpRequest(): bool
    {
        return ! self::isConsole() || self::isOctane();
    }

    /** Octane workers run in a CLI process but serve real HTTP requests. */
    private static function isOctane(): bool
    {
        return self::$isOctane ??= (bool) (getenv('LARAVEL_OCTANE') ?: ($_SERVER['LARAVEL_OCTANE'] ?? false));
    }

    public static function hostname(): string
    {
        return self::$hostname ??= (gethostname() ?: 'unknown');
    }

    /** Reset for tests / queue workers. */
    public static function resetCache(): void
    {
        self::$isWebContext = null;
        self::$isConsole = null;
        self::$isOctane = null;
        self::$hostname = null;
    }
}
