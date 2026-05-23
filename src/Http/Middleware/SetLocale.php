<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $available = config('activitylog-browse.browse.available_locales', ['en', 'ar']);
        $default   = config('activitylog-browse.browse.default_locale', 'en');
        $locale    = session('activitylog-browse-locale');

        if (! $locale || ! in_array($locale, $available, true)) {
            $locale = in_array($default, $available, true) ? $default : ($available[0] ?? 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
