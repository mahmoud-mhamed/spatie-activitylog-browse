<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Auth;

class SessionDataCollector
{
    private static ?string $cachedGuard = null;
    private static bool $guardDetected = false;

    public static function collect(): array
    {
        if (app()->runningInConsole()) {
            return [];
        }

        $config = config('activitylog-browse.session_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['auth_guard'] ?? false) {
            $guard = self::resolveGuard();
            if ($guard !== null) {
                $data['auth_guard'] = $guard;
            }
        }

        return $data ? ['session_data' => $data] : [];
    }

    public static function resetCache(): void
    {
        self::$cachedGuard = null;
        self::$guardDetected = false;
    }

    protected static function resolveGuard(): ?string
    {
        if (self::$guardDetected) {
            return self::$cachedGuard;
        }

        self::$guardDetected = true;

        $guards = array_keys(config('auth.guards', []));

        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    return self::$cachedGuard = $guard;
                }
            } catch (\Throwable) {
                // Guard may not be available
            }
        }

        return self::$cachedGuard = null;
    }
}
