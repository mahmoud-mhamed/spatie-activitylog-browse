<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Mhamed\SpatieActivitylogBrowse\Helpers\RelationDiscovery;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::with(['causer'])->orderByDesc('id');

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

        $logNames = Cache::remember('activitylog-browse:log_names', 60, fn () => $activityModel::distinct()->pluck('log_name')->sort()->values());
        $events = Cache::remember('activitylog-browse:events', 60, fn () => $activityModel::distinct()->whereNotNull('event')->pluck('event')->sort()->values());
        $subjectTypes = Cache::remember('activitylog-browse:subject_types', 60, fn () => $activityModel::distinct()->whereNotNull('subject_type')->pluck('subject_type')->sort()->values());
        $causerTypes = Cache::remember('activitylog-browse:causer_types', 60, fn () => $activityModel::distinct()->whereNotNull('causer_type')->pluck('causer_type')->sort()->values());

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
            ->orderByDesc('id')
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

    public function causers(Request $request)
    {
        $this->authorize();

        $causerType = $request->input('causer_type');

        if (! $causerType) {
            return response()->json([]);
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        $causerIds = $activityModel::where('causer_type', $causerType)
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        if ($causerIds->isEmpty()) {
            return response()->json([]);
        }

        $causerClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($causerType) ?? $causerType;

        if (class_exists($causerClass)) {
            try {
                $instance = new $causerClass;
                $models = $causerClass::whereIn($instance->getKeyName(), $causerIds)->get();

                return response()->json($models->map(function ($model) {
                    $name = $model->name ?? $model->email ?? $model->title ?? null;
                    $label = $name
                        ? $name . ' (#' . $model->getKey() . ')'
                        : '#' . $model->getKey();

                    return ['value' => (string) $model->getKey(), 'label' => $label];
                })->sortBy('label')->values());
            } catch (\Throwable) {
                // Fall through to ID-only fallback
            }
        }

        return response()->json(
            $causerIds->sort()->map(fn ($id) => ['value' => (string) $id, 'label' => '#' . $id])->values()
        );
    }

    public function subjectAttributes($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);

        if (! $activity->subject_type || ! $activity->subject) {
            return response()->json([]);
        }

        $excluded = config('activitylog-browse.auto_log.excluded_attributes', []);
        $attrs = array_diff_key($activity->subject->getAttributes(), array_flip($excluded));
        ksort($attrs);

        return response()->json($attrs);
    }

    public function show($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::with(['subject', 'causer'])->findOrFail($id);

        $relations = [];
        if ($activity->subject) {
            $relations = RelationDiscovery::getRelations($activity->subject);
        }

        return view('activitylog-browse::show', compact('activity', 'relations'));
    }

    public function relatedLogs(Request $request, $id, string $relation)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::with('subject')->findOrFail($id);

        if (! $activity->subject) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $subject = $activity->subject;
        $relations = RelationDiscovery::getRelations($subject);

        if (! in_array($relation, $relations)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $relatedQuery = $subject->$relation();
        $relatedModel = $relatedQuery->getRelated();
        $relatedType = $relatedModel->getMorphClass();
        $relatedIds = $relatedQuery->pluck($relatedModel->getQualifiedKeyName())->all();

        $perPage = config('activitylog-browse.browse.per_page', 25);

        if (empty($relatedIds)) {
            $activities = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);

            return view('activitylog-browse::related-logs', compact('activity', 'relation', 'activities'));
        }

        $query = $activityModel::with(['causer'])
            ->where('subject_type', $relatedType)
            ->whereIn('subject_id', $relatedIds)
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $activities = $query->paginate($perPage)->withQueryString();

        return view('activitylog-browse::related-logs', compact('activity', 'relation', 'activities'));
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
