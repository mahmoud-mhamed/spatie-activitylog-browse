<tr class="hover:bg-gray-50">
    <td class="px-4 py-3 text-sm">
        <a href="{{ route('activitylog-browse.show', $activity->id) }}" data-tip="{{ __('activitylog-browse::messages.tip_view_details') }}" data-tip-align="start"
           class="font-medium text-blue-600 hover:text-blue-800 hover:underline tabular-nums">{{ $activity->id }}</a>
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
        @php
            $createdAt = \Illuminate\Support\Carbon::parse($activity->created_at);
            // Clicking a value adds it to the current filters (back to page 1).
            $filterUrl = fn (array $query) => request()->fullUrlWithQuery($query + ['page' => null]);
        @endphp
        <div>{{ $createdAt->diffForHumans() }}</div>
        <a href="{{ $filterUrl(['date_from' => $createdAt->format('Y-m-d\TH:i:s'), 'date_to' => $createdAt->format('Y-m-d\TH:i:s')]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_datetime') }}"
           class="text-xs text-gray-400 tabular-nums hover:text-blue-600 hover:underline"><span dir="ltr">{{ $createdAt->format('Y-m-d H:i:s') }}</span></a>
    </td>
    <td class="px-4 py-3 text-sm">
        <a href="{{ $filterUrl(['log_name' => $activity->log_name]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_log') }}"
           class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 hover:underline">
            {{ $activity->log_name }}
        </a>
    </td>
    <td class="px-4 py-3 text-sm">
        @php
            $eventColors = [
                'created' => 'bg-green-100 text-green-800',
                'updated' => 'bg-blue-100 text-blue-800',
                'deleted' => 'bg-red-100 text-red-800',
            ];
            $color = $eventColors[$activity->event] ?? 'bg-gray-100 text-gray-800';
            $props = $activity->properties?->toArray() ?? [];
            // Payloads for the shared details dialog in index.blade.php (data is fetched on open).
            $changesDialog = [
                'type' => 'changes',
                'id' => $activity->id,
                'title' => __('activitylog-browse::messages.changes') . ' #' . $activity->id,
                'url' => route('activitylog-browse.changes', $activity->id),
            ];
        @endphp
        <div class="flex items-center gap-2">
            @if($activity->event)
                <a href="{{ $filterUrl(['event' => $activity->event]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_event') }}"
                   class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }} hover:underline">{{ $activity->event }}</a>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">-</span>
            @endif
            @if(! empty($props['old']) || ! empty($props['attributes']))
                <div x-data class="relative group">
                    <button type="button" @click="$dispatch('open-details', @js($changesDialog))"
                            class="flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.quick_preview') }}
                    </span>
                </div>
            @endif
        </div>
    </td>
    <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($activity->description, 60) }}</td>
    <td class="px-4 py-3 text-sm text-gray-500">
        @if($activity->subject_type)
            @php
                $subjectDialog = [
                    'type' => 'attributes',
                    'id' => $activity->id,
                    'title' => __('activitylog-browse::messages.current_attributes') . ' — ' . class_basename($activity->subject_type) . ' #' . $activity->subject_id,
                    'url' => route('activitylog-browse.subject-attributes', $activity->id),
                    'emptyText' => __('activitylog-browse::messages.model_deleted'),
                ];
            @endphp
            <div class="flex items-center gap-2">
                <span>
                    <a href="{{ $filterUrl(['subject_type' => $activity->subject_type, 'subject_id' => null, 'changed_attribute' => null]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_model_type') }}" class="hover:text-blue-600 hover:underline">{{ class_basename($activity->subject_type) }}</a>
                    <a href="{{ $filterUrl(['subject_type' => $activity->subject_type, 'subject_id' => $activity->subject_id]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_model') }}" class="text-gray-400 hover:text-blue-600 hover:underline">#{{ $activity->subject_id }}</a>
                </span>
                <div x-data class="relative group">
                    <button type="button" @click="$dispatch('open-details', @js($subjectDialog))"
                            class="flex items-center text-gray-400 hover:text-purple-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.current_attributes') }}
                    </span>
                </div>
                <div class="relative group">
                    <a href="{{ route('activitylog-browse.index', ['subject_type' => $activity->subject_type, 'subject_id' => $activity->subject_id]) }}"
                       class="flex items-center text-gray-400 hover:text-orange-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </a>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.model_logs') }}
                    </span>
                </div>
                <div class="relative group">
                    <a href="{{ route('activitylog-browse.timeline', ['subject_type' => $activity->subject_type, 'subject_id' => $activity->subject_id]) }}"
                       class="flex items-center text-gray-400 hover:text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h6" />
                        </svg>
                    </a>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.tip_timeline') }}
                    </span>
                </div>
            </div>
        @else
            -
        @endif
    </td>
    <td class="px-4 py-3 text-sm text-gray-500">
        @if($activity->causer)
            @php
                $causerName = $activity->causer->name
                    ?? $activity->causer->title
                    ?? trim(($activity->causer->first_name ?? '') . ' ' . ($activity->causer->last_name ?? ''))
                    ?: null;
                $causerDialog = [
                    'type' => 'attributes',
                    'id' => $activity->id,
                    'title' => __('activitylog-browse::messages.causer_attributes') . ' — ' . class_basename($activity->causer_type) . ' #' . $activity->causer_id,
                    'url' => route('activitylog-browse.causer-attributes', $activity->id),
                    'emptyText' => __('activitylog-browse::messages.causer_deleted'),
                ];
            @endphp
            <div class="flex items-center gap-2">
                <div>
                    <span>
                        <a href="{{ $filterUrl(['causer_type' => $activity->causer_type, 'causer_id' => null]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_causer_type') }}" class="hover:text-blue-600 hover:underline">{{ class_basename($activity->causer_type) }}</a>
                        <a href="{{ $filterUrl(['causer_type' => $activity->causer_type, 'causer_id' => $activity->causer_id]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_causer') }}" class="text-gray-400 hover:text-blue-600 hover:underline">#{{ $activity->causer_id }}</a>
                    </span>
                    @if($causerName)
                        <div class="text-xs text-gray-400 truncate max-w-[10rem]">{{ $causerName }}</div>
                    @endif
                </div>
                <div x-data class="relative group">
                    <button type="button" @click="$dispatch('open-details', @js($causerDialog))"
                            class="flex items-center text-gray-400 hover:text-purple-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.causer_attributes') }}
                    </span>
                </div>
            </div>
        @else
            <span class="text-gray-400">{{ __('activitylog-browse::messages.system') }}</span>
        @endif
    </td>
    <td class="px-4 py-3 text-sm">
        @php
            $methodColors = \Mhamed\SpatieActivitylogBrowse\Http\Controllers\ActivityLogController::METHOD_COLORS;
            $executionSource = is_string($props['execution_context']['source'] ?? null) ? $props['execution_context']['source'] : null;
            // Queue/schedule/console rows carry a placeholder request (APP_URL, GET): show where they came from instead.
            $requestMethod = ($executionSource === null || $executionSource === 'web') && is_string($props['request_data']['method'] ?? null)
                ? $props['request_data']['method']
                : null;
            $sourceLabelKey = "activitylog-browse::messages.source_{$executionSource}";
            // Only read the attribute when the column exists (strict models throw on missing attributes).
            $requestId = ($hasRequestId ?? false) ? $activity->request_id : null;
            $requestCount = $requestId ? (int) (($requestCounts ?? [])[$requestId] ?? 0) : 0;
            $hasRequestDetails = (bool) array_diff(array_keys($props), ['old', 'attributes']);
            $requestDialog = [
                'type' => 'request',
                'id' => $activity->id,
                'title' => __('activitylog-browse::messages.request_details') . ' #' . $activity->id,
                'url' => route('activitylog-browse.request-details', $activity->id),
            ];
        @endphp
        <div class="flex items-center gap-2">
            @if($requestMethod)
                <a href="{{ $filterUrl(['method' => $requestMethod]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_method') }}" data-tip-align="end"
                   class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $methodColors[strtoupper($requestMethod)] ?? 'bg-gray-100 text-gray-800' }} hover:underline">{{ $requestMethod }}</a>
            @elseif($executionSource)
                <a href="{{ $filterUrl(['source' => $executionSource]) }}" data-tip="{{ __('activitylog-browse::messages.tip_filter_source') }}" data-tip-align="end"
                   class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 hover:underline">{{ \Illuminate\Support\Facades\Lang::has($sourceLabelKey) ? __($sourceLabelKey) : $executionSource }}</a>
            @else
                <span class="text-gray-400">-</span>
            @endif

            @if($requestId)
                <div class="relative group">
                    <a href="{{ route('activitylog-browse.index', ['request_id' => $requestId]) }}"
                       class="inline-flex items-center gap-0.5 text-gray-400 hover:text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        @if($requestCount > 1)
                            <span class="text-xs font-medium tabular-nums">{{ $requestCount }}</span>
                        @endif
                    </a>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.request_changes') }}
                    </span>
                </div>
            @endif

            @if($hasRequestDetails)
                <div x-data class="relative group">
                    <button type="button"
                            @click="$dispatch('open-details', @js($requestDialog))"
                            class="flex items-center text-gray-400 hover:text-green-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </button>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.request_details') }}
                    </span>
                </div>
            @endif
        </div>
    </td>
</tr>
