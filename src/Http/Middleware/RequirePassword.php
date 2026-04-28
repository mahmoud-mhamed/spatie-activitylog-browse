<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePassword
{
    public const SESSION_KEY = 'activitylog-browse:authenticated';

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $whitelisted = ['activitylog-browse.login', 'activitylog-browse.login.submit', 'activitylog-browse.logout'];

        if (in_array($routeName, $whitelisted, true)) {
            return $next($request);
        }

        $password = config('activitylog-browse.browse.password');

        if ($password === null || $password === '') {
            return $next($request);
        }

        if (! $request->session()->get(self::SESSION_KEY)) {
            return redirect()->route('activitylog-browse.login');
        }

        return $next($request);
    }
}
