<?php

use Illuminate\Support\Facades\Route;
use Mhamed\SpatieActivitylogBrowse\Http\Controllers\ActivityLogController;
use Mhamed\SpatieActivitylogBrowse\Http\Middleware\SetLocale;

$prefix = config('activitylog-browse.browse.prefix', 'activity-log');
$middleware = config('activitylog-browse.browse.middleware', ['web', 'auth']);

Route::middleware(array_merge($middleware, [SetLocale::class]))
    ->prefix($prefix)
    ->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('activitylog-browse.index');
        Route::get('/attributes', [ActivityLogController::class, 'attributes'])->name('activitylog-browse.attributes');
        Route::get('/switch-lang/{locale}', [ActivityLogController::class, 'switchLang'])->name('activitylog-browse.switch-lang');
        Route::get('/{activity}', [ActivityLogController::class, 'show'])->name('activitylog-browse.show');
    });
