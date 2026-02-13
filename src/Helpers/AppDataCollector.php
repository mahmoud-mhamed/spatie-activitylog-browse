<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Request;

class AppDataCollector
{
    public static function collect(): array
    {
        if (app()->runningInConsole() && ! Request::instance()->getHost()) {
            return [];
        }

        $config = config('activitylog-browse.app_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['environment'] ?? false) {
            $data['environment'] = app()->environment();
        }

        if ($fields['php_version'] ?? false) {
            $data['php_version'] = PHP_VERSION;
        }

        if ($fields['server_hostname'] ?? false) {
            $data['server_hostname'] = gethostname();
        }

        return $data ? ['app_data' => $data] : [];
    }
}
