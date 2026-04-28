<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * Lightweight DB query counter that hooks into `DB::listen()` once per process
 * and increments a single integer per executed query.
 *
 * The default Laravel query log (`DB::enableQueryLog()`) is OFF in production,
 * so `count(DB::getQueryLog())` reports 0. This counter sidesteps that without
 * paying the cost of buffering every query with bindings + time in memory.
 */
class QueryCounter
{
    private static int $count = 0;
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        try {
            DB::listen(function () {
                self::$count++;
            });
        } catch (\Throwable) {
            // Database manager may not be ready in some boot orders.
        }
    }

    public static function count(): int
    {
        return self::$count;
    }

    public static function reset(): void
    {
        self::$count = 0;
    }
}
