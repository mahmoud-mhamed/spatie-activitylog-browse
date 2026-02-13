<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Auth;

class SessionDataCollector
{
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
            $guards = array_keys(config('auth.guards', []));

            foreach ($guards as $guard) {
                try {
                    if (Auth::guard($guard)->check()) {
                        $data['auth_guard'] = $guard;
                        break;
                    }
                } catch (\Throwable) {
                    // Guard may not be available
                }
            }
        }

        return $data ? ['session_data' => $data] : [];
    }
}
