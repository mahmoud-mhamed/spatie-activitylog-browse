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
                @php
                    $allKeys = array_unique(array_merge(array_keys($old ?? []), array_keys($attributes ?? [])));
                    sort($allKeys);
                    $diffRows = [];
                    foreach ($allKeys as $key) {
                        $diffRows[] = [
                            'key' => $key,
                            'old' => $old ? Str::limit(is_array($old[$key] ?? null) ? json_encode($old[$key]) : (string) ($old[$key] ?? '-'), 30) : null,
                            'new' => $attributes ? Str::limit(is_array($attributes[$key] ?? null) ? json_encode($attributes[$key]) : (string) ($attributes[$key] ?? '-'), 30) : null,
                        ];
                    }
                @endphp
                <div x-data="{
                    open: false,
                    pos: { top: 0, left: 0, above: true },
                    diffSearch: '',
                    hideEmpty: false,
                    rows: @js($diffRows),
                    hasOld: @js((bool) $old),
                    hasNew: @js((bool) $attributes),
                    isEmpty(row) {
                        let oldEmpty = !row.old || row.old === '-' || row.old === '';
                        let newEmpty = !row.new || row.new === '-' || row.new === '';
                        return oldEmpty && newEmpty;
                    },
                    get filteredRows() {
                        let result = this.rows;
                        if (this.hideEmpty) {
                            result = result.filter(r => !this.isEmpty(r));
                        }
                        if (!this.diffSearch) return result;
                        let s = this.diffSearch.toLowerCase();
                        return result.filter(r => r.key.toLowerCase().includes(s) || (r.old && r.old.toLowerCase().includes(s)) || (r.new && r.new.toLowerCase().includes(s)));
                    }
                }" class="relative group">
                    <button @click="
                        let rect = $el.getBoundingClientRect();
                        let isRtl = document.documentElement.dir === 'rtl';
                        let popW = 640, popH = 384;
                        let left = isRtl ? rect.right : rect.left - popW;
                        if (left + popW > window.innerWidth - 8) left = window.innerWidth - popW - 8;
                        if (left < 8) left = 8;
                        let above = rect.top - popH > 8;
                        let top = above ? rect.top - 8 : rect.bottom + 8;
                        pos = { top, left, above };
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
                             :class="pos.above ? '-translate-y-full' : ''"
                             class="fixed z-[9999] w-[40rem] max-h-96 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 p-3">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="text-xs font-semibold text-gray-500 uppercase shrink-0">{{ __('activitylog-browse::messages.changes') }}</div>
                                <div class="flex items-center gap-2">
                                    <label @click.stop class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer whitespace-nowrap select-none">
                                        <input type="checkbox" x-model="hideEmpty" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-3 w-3">
                                        {{ __('activitylog-browse::messages.hide_empty') }}
                                    </label>
                                    <template x-if="rows.length > 5">
                                        <input type="text" x-model="diffSearch"
                                               @click.stop
                                               placeholder="{{ __('activitylog-browse::messages.search') }}..."
                                               class="rounded border-gray-300 text-xs px-2 py-1 border focus:border-blue-500 focus:ring-blue-500 w-32">
                                    </template>
                                </div>
                            </div>
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="text-start py-1 pe-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.attr') }}</th>
                                        <th x-show="hasOld" class="text-start py-1 pe-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.old') }}</th>
                                        <th x-show="hasNew" class="text-start py-1 text-gray-500 font-medium">{{ __('activitylog-browse::messages.new') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in filteredRows" :key="row.key">
                                        <tr class="border-b border-gray-50">
                                            <td class="py-1 pe-2 font-medium text-gray-700" x-text="translateAttribute(row.key)" :title="row.key"></td>
                                            <td x-show="hasOld" class="py-1 pe-2 text-red-600" x-text="row.old"></td>
                                            <td x-show="hasNew" class="py-1 text-green-600" x-text="row.new"></td>
                                        </tr>
                                    </template>
                                    <template x-if="filteredRows.length === 0 && diffSearch">
                                        <tr>
                                            <td colspan="3" class="py-2 text-gray-400 italic">{{ __('activitylog-browse::messages.no_data') }}</td>
                                        </tr>
                                    </template>
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
                    <div x-data="{
                        open: false,
                        pos: { top: 0, left: 0, above: true },
                        loading: false,
                        attrs: null,
                        attrSearch: '',
                        hideEmpty: false,
                        isEmpty(v) {
                            return v === null || v === '' || v === 0 || v === '0' || v === false;
                        },
                        get filteredKeys() {
                            if (!this.attrs) return [];
                            let keys = Object.keys(this.attrs).sort();
                            if (this.hideEmpty) {
                                keys = keys.filter(k => !this.isEmpty(this.attrs[k]));
                            }
                            if (!this.attrSearch) return keys;
                            let s = this.attrSearch.toLowerCase();
                            return keys.filter(k => k.toLowerCase().includes(s) || String(this.attrs[k] ?? '').toLowerCase().includes(s));
                        }
                    }" class="relative group">
                        <button @click="
                            let rect = $el.getBoundingClientRect();
                            let isRtl = document.documentElement.dir === 'rtl';
                            let popW = 640, popH = 384;
                            let left = isRtl ? rect.right : rect.left - popW;
                            if (left + popW > window.innerWidth - 8) left = window.innerWidth - popW - 8;
                            if (left < 8) left = 8;
                            let above = rect.top - popH > 8;
                            let top = above ? rect.top - 8 : rect.bottom + 8;
                            pos = { top, left, above };
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
                                 :class="pos.above ? '-translate-y-full' : ''"
                                 class="fixed z-[9999] w-[40rem] max-h-96 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 p-3">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <div class="text-xs font-semibold text-gray-500 uppercase shrink-0">{{ __('activitylog-browse::messages.current_attributes') }}</div>
                                    <template x-if="!loading && attrs !== null && Object.keys(attrs).length > 5">
                                        <div class="flex items-center gap-2">
                                            <label @click.stop class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer whitespace-nowrap select-none">
                                                <input type="checkbox" x-model="hideEmpty" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-3 w-3">
                                                {{ __('activitylog-browse::messages.hide_empty') }}
                                            </label>
                                            <input type="text" x-model="attrSearch"
                                                   @click.stop
                                                   placeholder="{{ __('activitylog-browse::messages.search') }}..."
                                                   class="rounded border-gray-300 text-xs px-2 py-1 border focus:border-blue-500 focus:ring-blue-500 w-32">
                                        </div>
                                    </template>
                                </div>
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
                                                <th class="text-start py-1 pe-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.attr') }}</th>
                                                <th class="text-start py-1 text-gray-500 font-medium">{{ __('activitylog-browse::messages.value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="key in filteredKeys" :key="key">
                                                <tr class="border-b border-gray-50">
                                                    <td class="py-1 pe-2 font-medium text-gray-700" x-text="translateAttribute(key)" :title="key"></td>
                                                    <td class="py-1 text-gray-600" x-text="typeof attrs[key] === 'object' && attrs[key] !== null ? JSON.stringify(attrs[key]).substring(0, 30) : String(attrs[key] ?? '').substring(0, 30)"></td>
                                                </tr>
                                            </template>
                                            <template x-if="filteredKeys.length === 0 && attrSearch">
                                                <tr>
                                                    <td colspan="2" class="py-2 text-gray-400 italic">{{ __('activitylog-browse::messages.no_data') }}</td>
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
            <div class="flex items-center gap-2">
                <div>
                    <span>{{ class_basename($activity->causer_type) }} <span class="text-gray-400">#{{ $activity->causer_id }}</span></span>
                    @php
                        $causerName = $activity->causer->name
                            ?? $activity->causer->title
                            ?? trim(($activity->causer->first_name ?? '') . ' ' . ($activity->causer->last_name ?? ''))
                            ?: null;
                    @endphp
                    @if($causerName)
                        <div class="text-xs text-gray-400 truncate max-w-[10rem]">{{ $causerName }}</div>
                    @endif
                </div>
                <div x-data="{
                    open: false,
                    pos: { top: 0, left: 0, above: true },
                    loading: false,
                    attrs: null,
                    attrSearch: '',
                    hideEmpty: false,
                    isEmpty(v) {
                        return v === null || v === '' || v === 0 || v === '0' || v === false;
                    },
                    get filteredKeys() {
                        if (!this.attrs) return [];
                        let keys = Object.keys(this.attrs).sort();
                        if (this.hideEmpty) {
                            keys = keys.filter(k => !this.isEmpty(this.attrs[k]));
                        }
                        if (!this.attrSearch) return keys;
                        let s = this.attrSearch.toLowerCase();
                        return keys.filter(k => k.toLowerCase().includes(s) || String(this.attrs[k] ?? '').toLowerCase().includes(s));
                    }
                }" class="relative group">
                    <button @click="
                        let rect = $el.getBoundingClientRect();
                        let isRtl = document.documentElement.dir === 'rtl';
                        let popW = 640, popH = 384;
                        let left = isRtl ? rect.right : rect.left - popW;
                        if (left + popW > window.innerWidth - 8) left = window.innerWidth - popW - 8;
                        if (left < 8) left = 8;
                        let above = rect.top - popH > 8;
                        let top = above ? rect.top - 8 : rect.bottom + 8;
                        pos = { top, left, above };
                        if (!open && attrs === null) {
                            loading = true;
                            fetch('{{ route('activitylog-browse.causer-attributes', $activity->id) }}')
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
                        {{ __('activitylog-browse::messages.causer_attributes') }}
                    </span>
                    <template x-teleport="body">
                        <div x-show="open" x-transition @click.outside="open = false"
                             :style="'top:' + pos.top + 'px;left:' + pos.left + 'px'"
                             :class="pos.above ? '-translate-y-full' : ''"
                             class="fixed z-[9999] w-[40rem] max-h-96 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 p-3">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="text-xs font-semibold text-gray-500 uppercase shrink-0">{{ __('activitylog-browse::messages.causer_attributes') }}</div>
                                <template x-if="!loading && attrs !== null && Object.keys(attrs).length > 5">
                                    <div class="flex items-center gap-2">
                                        <label @click.stop class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer whitespace-nowrap select-none">
                                            <input type="checkbox" x-model="hideEmpty" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-3 w-3">
                                            {{ __('activitylog-browse::messages.hide_empty') }}
                                        </label>
                                        <input type="text" x-model="attrSearch"
                                               @click.stop
                                               placeholder="{{ __('activitylog-browse::messages.search') }}..."
                                               class="rounded border-gray-300 text-xs px-2 py-1 border focus:border-blue-500 focus:ring-blue-500 w-32">
                                    </div>
                                </template>
                            </div>
                            <template x-if="loading">
                                <div class="flex justify-center py-4">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>
                            </template>
                            <template x-if="!loading && attrs !== null && Object.keys(attrs).length === 0">
                                <div class="text-xs text-gray-400 italic py-2">{{ __('activitylog-browse::messages.causer_deleted') }}</div>
                            </template>
                            <template x-if="!loading && attrs !== null && Object.keys(attrs).length > 0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-100">
                                            <th class="text-start py-1 pe-2 text-gray-500 font-medium">{{ __('activitylog-browse::messages.attr') }}</th>
                                            <th class="text-start py-1 text-gray-500 font-medium">{{ __('activitylog-browse::messages.value') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="key in filteredKeys" :key="key">
                                            <tr class="border-b border-gray-50">
                                                <td class="py-1 pe-2 font-medium text-gray-700" x-text="translateAttribute(key)" :title="key"></td>
                                                <td class="py-1 text-gray-600" x-text="typeof attrs[key] === 'object' && attrs[key] !== null ? JSON.stringify(attrs[key]).substring(0, 30) : String(attrs[key] ?? '').substring(0, 30)"></td>
                                            </tr>
                                        </template>
                                        <template x-if="filteredKeys.length === 0 && attrSearch">
                                            <tr>
                                                <td colspan="2" class="py-2 text-gray-400 italic">{{ __('activitylog-browse::messages.no_data') }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
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
