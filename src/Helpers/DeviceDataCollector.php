<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Request;

class DeviceDataCollector
{
    public static function collect(): array
    {
        // Outside a real HTTP request the "client" is the placeholder request (127.0.0.1, "Symfony").
        if (! RuntimeContext::isHttpRequest()) {
            return [];
        }

        $config = config('activitylog-browse.device_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['ip'] ?? false) {
            $data['ip'] = RequestDataCollector::scrub(Request::ip());
        }

        if ($fields['user_agent'] ?? false) {
            $data['user_agent'] = RequestDataCollector::scrub(Request::userAgent());
        }

        if ($fields['referrer'] ?? false) {
            $data['referrer'] = RequestDataCollector::scrub(Request::header('referer'));
        }

        return $data ? ['device_data' => $data] : [];
    }
}
