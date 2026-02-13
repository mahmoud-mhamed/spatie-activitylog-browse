<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Request;

class DeviceDataCollector
{
    public static function collect(): array
    {
        if (app()->runningInConsole() && ! Request::instance()->getHost()) {
            return [];
        }

        $config = config('activitylog-browse.device_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['ip'] ?? false) {
            $data['ip'] = Request::ip();
        }

        if ($fields['user_agent'] ?? false) {
            $data['user_agent'] = Request::userAgent();
        }

        if ($fields['referrer'] ?? false) {
            $data['referrer'] = Request::header('referer');
        }

        return $data ? ['device_data' => $data] : [];
    }
}
