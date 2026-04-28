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

    public static function hostname(): string
    {
        return self::$hostname ??= (gethostname() ?: 'unknown');
    }

    /** Reset for tests / queue workers. */
    public static function resetCache(): void
    {
        self::$isWebContext = null;
        self::$isConsole = null;
        self::$hostname = null;
    }
}
