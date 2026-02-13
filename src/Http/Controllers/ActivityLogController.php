<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::with(['subject', 'causer'])->latest();

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('causer_type')) {
            $query->where('causer_type', $request->input('causer_type'));
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->input('causer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('changed_attribute')) {
            $attr = $request->input('changed_attribute');
            $query->where(function ($q) use ($attr) {
                $q->whereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.attributes.' . $attr])
                  ->orWhereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.old.' . $attr]);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        $activities = $query->paginate(
            config('activitylog-browse.browse.per_page', 25)
        )->withQueryString();

        $logNames = $activityModel::distinct()->pluck('log_name')->sort()->values();
        $events = $activityModel::distinct()->whereNotNull('event')->pluck('event')->sort()->values();
        $subjectTypes = $activityModel::distinct()->whereNotNull('subject_type')->pluck('subject_type')->sort()->values();
        $causerTypes = $activityModel::distinct()->whereNotNull('causer_type')->pluck('causer_type')->sort()->values();

        return view('activitylog-browse::index', compact(
            'activities',
            'logNames',
            'events',
            'subjectTypes',
            'causerTypes',
        ));
    }

    public function attributes(Request $request)
    {
        $this->authorize();

        $subjectType = $request->input('subject_type');

        if (! $subjectType) {
            return response()->json([]);
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        $activities = $activityModel::where('subject_type', $subjectType)
            ->whereNotNull('properties')
            ->latest()
            ->limit(100)
            ->pluck('properties');

        $keys = collect();

        foreach ($activities as $properties) {
            $props = $properties instanceof \Illuminate\Support\Collection ? $properties->toArray() : (array) $properties;

            if (isset($props['attributes']) && is_array($props['attributes'])) {
                $keys = $keys->merge(array_keys($props['attributes']));
            }
            if (isset($props['old']) && is_array($props['old'])) {
                $keys = $keys->merge(array_keys($props['old']));
            }
        }

        return response()->json($keys->unique()->sort()->values());
    }

    public function show($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::with(['subject', 'causer'])->findOrFail($id);

        return view('activitylog-browse::show', compact('activity'));
    }

    public function switchLang(string $locale)
    {
        $availableLocales = config('activitylog-browse.browse.available_locales', ['en', 'ar']);

        if (! in_array($locale, $availableLocales)) {
            abort(Response::HTTP_BAD_REQUEST);
        }

        session(['activitylog-browse-locale' => $locale]);

        return redirect()->back();
    }

    protected function authorize(): void
    {
        $gate = config('activitylog-browse.browse.gate');

        if ($gate && Gate::has($gate)) {
            Gate::authorize($gate);
        }
    }
}
