<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = session('activitylog-browse-locale');

        if ($locale && in_array($locale, config('activitylog-browse.browse.available_locales', ['en', 'ar']))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
