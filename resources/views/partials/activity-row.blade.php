<tr class="hover:bg-gray-50">
    <td class="px-4 py-3 text-sm text-gray-500">{{ $activity->id }}</td>
    <td class="px-4 py-3 text-sm text-gray-500" title="{{ $activity->created_at }}">
        {{ $activity->created_at->diffForHumans() }}
    </td>
    <td class="px-4 py-3 text-sm">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
            {{ $activity->log_name }}
        </span>
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
            $old = $props['old'] ?? null;
            $attributes = $props['attributes'] ?? null;
        @endphp
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
                {{ $activity->event ?? '-' }}
            </span>
            @if($old || $attributes)
                <div x-data="{ open: false, pos: { top: 0, left: 0 } }" class="relative group">
                    <button @click="
                        let rect = $el.getBoundingClientRect();
                        pos = { top: rect.top + window.scrollY - 8, left: rect.left + window.scrollX - 320 };
                        open = !open;
                    " type="button"
                            class="text-gray-400 mt-1 hover:text-gray-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.quick_preview') }}
                    </span>
                    <template x-teleport="body">
                        <div x-show="open" x-transition @click.outside="open = false"
                             :style="'top:' + pos.top + 'px;left:' + pos.left + 'px'"
                             class="fixed z-[9999] w-80 bg-white rounded-lg shadow-lg border border-gray-200 p-3 -translate-y-full">
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('activitylog-browse::messages.changes') }}</div>
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="text-left py-1 pr-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.attr') }}</th>
                                        @if($old)<th class="text-left py-1 pr-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.old') }}</th>@endif
                                        @if($attributes)<th class="text-left py-1 text-gray-500 font-medium">{{ __('activitylog-browse::messages.new') }}</th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $allKeys = array_unique(array_merge(array_keys($old ?? []), array_keys($attributes ?? []))); sort($allKeys); @endphp
                                    @foreach($allKeys as $key)
                                        <tr class="border-b border-gray-50">
                                            <td class="py-1 pr-2 font-medium text-gray-700">{{ $key }}</td>
                                            @if($old)
                                                <td class="py-1 pr-2 text-red-600">{{ Str::limit(is_array($old[$key] ?? null) ? json_encode($old[$key]) : ($old[$key] ?? '-'), 30) }}</td>
                                            @endif
                                            @if($attributes)
                                                <td class="py-1 text-green-600">{{ Str::limit(is_array($attributes[$key] ?? null) ? json_encode($attributes[$key]) : ($attributes[$key] ?? '-'), 30) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            @endif
        </div>
    </td>
    <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($activity->description, 60) }}</td>
    <td class="px-4 py-3 text-sm text-gray-500">
        @if($activity->subject_type)
            <div class="flex items-center gap-2">
                <span>{{ class_basename($activity->subject_type) }} <span class="text-gray-400">#{{ $activity->subject_id }}</span></span>
                @if($activity->subject_type)
                    <div x-data="{ open: false, pos: { top: 0, left: 0 }, loading: false, attrs: null }" class="relative group">
                        <button @click="
                            let rect = $el.getBoundingClientRect();
                            pos = { top: rect.top + window.scrollY - 8, left: rect.left + window.scrollX - 320 };
                            if (!open && attrs === null) {
                                loading = true;
                                fetch('{{ route('activitylog-browse.subject-attributes', $activity->id) }}')
                                    .then(r => r.json())
                                    .then(data => { attrs = data; loading = false; })
                                    .catch(() => { attrs = {}; loading = false; });
                            }
                            open = !open;
                        " type="button"
                                class="text-gray-400 mt-1 hover:text-purple-600 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </button>
                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                            {{ __('activitylog-browse::messages.current_attributes') }}
                        </span>
                        <template x-teleport="body">
                            <div x-show="open" x-transition @click.outside="open = false"
                                 :style="'top:' + pos.top + 'px;left:' + pos.left + 'px'"
                                 class="fixed z-[9999] w-80 max-h-96 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 p-3 -translate-y-full">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('activitylog-browse::messages.current_attributes') }}</div>
                                <template x-if="loading">
                                    <div class="flex justify-center py-4">
                                        <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                </template>
                                <template x-if="!loading && attrs !== null && Object.keys(attrs).length === 0">
                                    <div class="text-xs text-gray-400 italic py-2">{{ __('activitylog-browse::messages.model_deleted') }}</div>
                                </template>
                                <template x-if="!loading && attrs !== null && Object.keys(attrs).length > 0">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="border-b border-gray-100">
                                                <th class="text-left py-1 pr-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.attr') }}</th>
                                                <th class="text-left py-1 text-gray-500 font-medium">{{ __('activitylog-browse::messages.value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="key in Object.keys(attrs).sort()" :key="key">
                                                <tr class="border-b border-gray-50">
                                                    <td class="py-1 pr-2 font-medium text-gray-700" x-text="key"></td>
                                                    <td class="py-1 text-gray-600" x-text="typeof attrs[key] === 'object' && attrs[key] !== null ? JSON.stringify(attrs[key]).substring(0, 30) : String(attrs[key] ?? '').substring(0, 30)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </template>
                            </div>
                        </template>
                    </div>
                @endif
            </div>
        @else
            -
        @endif
    </td>
    <td class="px-4 py-3 text-sm text-gray-500">
        @if($activity->causer)
            {{ class_basename($activity->causer_type) }}
            <span class="text-gray-400">#{{ $activity->causer_id }}</span>
        @else
            <span class="text-gray-400">{{ __('activitylog-browse::messages.system') }}</span>
        @endif
    </td>
    <td class="px-4 py-3 text-sm">
        <div class="flex items-center gap-5">

            {{-- View detail --}}
            <div class="relative group">
                <a href="{{ route('activitylog-browse.show', $activity->id) }}"
                   class="text-gray-400 hover:text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </a>
                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                    {{ __('activitylog-browse::messages.view') }}
                </span>
            </div>

            {{-- Link to all logs for this model --}}
            @if($activity->subject_type)
                <div class="relative group">
                    <a href="{{ route('activitylog-browse.index', ['subject_type' => $activity->subject_type, 'subject_id' => $activity->subject_id]) }}"
                       class="text-gray-400 hover:text-orange-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </a>
                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                        {{ __('activitylog-browse::messages.model_logs') }}
                    </span>
                </div>
            @endif
        </div>
    </td>
</tr>
