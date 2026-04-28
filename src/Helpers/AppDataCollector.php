<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

class AppDataCollector
{
    private static ?string $cachedEnv = null;

    public static function collect(): array
    {
        if (! RuntimeContext::isWebContext()) {
            return [];
        }

        $config = config('activitylog-browse.app_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['environment'] ?? false) {
            $data['environment'] = self::$cachedEnv ??= app()->environment();
        }

        if ($fields['php_version'] ?? false) {
            $data['php_version'] = PHP_VERSION;
        }

        if ($fields['server_hostname'] ?? false) {
            $data['server_hostname'] = RuntimeContext::hostname();
        }

        return $data ? ['app_data' => $data] : [];
    }

    public static function resetCache(): void
    {
        self::$cachedEnv = null;
    }
}
