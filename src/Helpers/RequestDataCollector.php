<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Support\Facades\Request;

class RequestDataCollector
{
    public static function collect(): array
    {
        if (app()->runningInConsole() && ! Request::instance()->getHost()) {
            return [];
        }

        $config = config('activitylog-browse.request_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['url'] ?? false) {
            $data['url'] = Request::fullUrl();
        }

        if ($fields['previous_url'] ?? false) {
            $data['previous_url'] = url()->previous();
        }

        if ($fields['method'] ?? false) {
            $data['method'] = Request::method();
        }

        if ($fields['route_name'] ?? false) {
            $data['route_name'] = Request::route()?->getName();
        }

        return $data ? ['request_data' => $data] : [];
    }
}
