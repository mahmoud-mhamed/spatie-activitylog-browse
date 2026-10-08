<?php

use Illuminate\Support\Facades\Route;
use Mhamed\SpatieActivitylogBrowse\Http\Controllers\ActivityLogController;
use Mhamed\SpatieActivitylogBrowse\Http\Middleware\RequirePassword;
use Mhamed\SpatieActivitylogBrowse\Http\Middleware\SetLocale;

$prefix = config('activitylog-browse.browse.prefix', 'activity-log');
$middleware = config('activitylog-browse.browse.middleware', ['web', 'auth']);

Route::middleware(array_merge($middleware, [SetLocale::class, RequirePassword::class]))
    ->prefix($prefix)
    ->group(function () {
        Route::get('/login', [ActivityLogController::class, 'showLogin'])->name('activitylog-browse.login');
        Route::post('/login', [ActivityLogController::class, 'authenticate'])->name('activitylog-browse.login.submit');
        Route::post('/logout', [ActivityLogController::class, 'logout'])->name('activitylog-browse.logout');

        Route::get('/', [ActivityLogController::class, 'index'])->name('activitylog-browse.index');
        Route::get('/statistics', [ActivityLogController::class, 'statistics'])->name('activitylog-browse.statistics');
        Route::get('/stats', [ActivityLogController::class, 'stats'])->name('activitylog-browse.stats');
        Route::get('/filter-options', [ActivityLogController::class, 'filterOptions'])->name('activitylog-browse.filter-options');
        Route::get('/attributes', [ActivityLogController::class, 'attributes'])->name('activitylog-browse.attributes');
        Route::get('/model-info', [ActivityLogController::class, 'modelInfo'])->name('activitylog-browse.model-info');
        Route::get('/causers', [ActivityLogController::class, 'causers'])->name('activitylog-browse.causers');
        Route::get('/switch-lang/{locale}', [ActivityLogController::class, 'switchLang'])->name('activitylog-browse.switch-lang');
        Route::get('/about', [ActivityLogController::class, 'about'])->name('activitylog-browse.about');
        Route::get('/timeline', [ActivityLogController::class, 'timeline'])->name('activitylog-browse.timeline');
        Route::get('/cleanup', [ActivityLogController::class, 'cleanup'])->name('activitylog-browse.cleanup');
        Route::get('/cleanup/preview', [ActivityLogController::class, 'cleanupPreview'])->name('activitylog-browse.cleanup-preview');
        Route::delete('/cleanup/delete', [ActivityLogController::class, 'cleanupDelete'])->name('activitylog-browse.cleanup-delete');
        Route::post('/cleanup/retention/run', [ActivityLogController::class, 'cleanupRunRetention'])->name('activitylog-browse.cleanup-retention');
        Route::get('/cleanup/bodies/preview', [ActivityLogController::class, 'cleanupBodiesPreview'])->name('activitylog-browse.cleanup-bodies-preview');
        Route::post('/cleanup/bodies', [ActivityLogController::class, 'cleanupBodies'])->name('activitylog-browse.cleanup-bodies');
        Route::get('/cleanup/placeholder/preview', [ActivityLogController::class, 'cleanupPlaceholderPreview'])->name('activitylog-browse.cleanup-placeholder-preview');
        Route::post('/cleanup/placeholder', [ActivityLogController::class, 'cleanupPlaceholder'])->name('activitylog-browse.cleanup-placeholder');
        Route::post('/cleanup/request-id', [ActivityLogController::class, 'cleanupEnsureRequestId'])->name('activitylog-browse.cleanup-request-id');
        Route::post('/cleanup/reclaim-space', [ActivityLogController::class, 'cleanupReclaimSpace'])->name('activitylog-browse.cleanup-reclaim-space');
        Route::get('/cleanup/table-size', [ActivityLogController::class, 'cleanupTableSize'])->name('activitylog-browse.cleanup-table-size');
        Route::get('/deletion-history', [ActivityLogController::class, 'deletionHistory'])->name('activitylog-browse.deletion-history');
        Route::delete('/deletion-history', [ActivityLogController::class, 'clearDeletionHistory'])->name('activitylog-browse.clear-deletion-history');
        Route::get('/{activity}/attributes', [ActivityLogController::class, 'subjectAttributes'])->name('activitylog-browse.subject-attributes');
        Route::get('/{activity}/causer-attributes', [ActivityLogController::class, 'causerAttributes'])->name('activitylog-browse.causer-attributes');
        Route::get('/{activity}/request-details', [ActivityLogController::class, 'requestDetails'])->name('activitylog-browse.request-details');
        Route::get('/{activity}/restore-preview', [ActivityLogController::class, 'restorePreview'])->name('activitylog-browse.restore-preview');
        Route::post('/{activity}/restore', [ActivityLogController::class, 'restore'])->name('activitylog-browse.restore');
        Route::get('/{activity}/changes', [ActivityLogController::class, 'changes'])->name('activitylog-browse.changes');
        Route::get('/{activity}/related/{relation}', [ActivityLogController::class, 'relatedLogs'])->name('activitylog-browse.related-logs');
        Route::get('/{activity}', [ActivityLogController::class, 'show'])->name('activitylog-browse.show');
    });
