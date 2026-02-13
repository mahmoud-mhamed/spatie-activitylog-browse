@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.statistics'))

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('activitylog-browse.index') }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ __('activitylog-browse::messages.back_to_list') }}
        </a>
    </div>

    @php
        $weekdays = __('activitylog-browse::messages.stats_weekdays');
        $statsUrl = route('activitylog-browse.stats');
        $attrTranslations = __('validation.attributes');
        if (!is_array($attrTranslations)) $attrTranslations = [];
    @endphp

    <div x-data="{
        weekdays: @js($weekdays),
        attrTranslations: @js($attrTranslations),
        baseUrl: '{{ $statsUrl }}',
        dateFrom: '',
        dateTo: '',
        activeFrom: '',
        activeTo: '',

        sections: {
            overview: { data: null, loading: true },
            events: { data: null, loading: true },
            log_names: { data: null, loading: true },
            models: { data: null, loading: true },
            causers: { data: null, loading: true },
            daily: { data: null, loading: true },
            hourly: { data: null, loading: true },
            weekday: { data: null, loading: true },
            system_user: { data: null, loading: true },
            batches: { data: null, loading: true },
            attributes: { data: null, loading: true },
            monthly: { data: null, loading: true },
            peak_day: { data: null, loading: true },
        },

        init() { this.fetchAll(); },

        fetchSection(name) {
            this.sections[name].loading = true;
            this.sections[name].data = null;
            let params = new URLSearchParams({ section: name });
            if (this.dateFrom) params.set('date_from', this.dateFrom);
            if (this.dateTo) params.set('date_to', this.dateTo);
            fetch(this.baseUrl + '?' + params.toString())
                .then(r => r.json())
                .then(d => { this.sections[name].data = d; this.sections[name].loading = false; })
                .catch(() => { this.sections[name].loading = false; });
        },

        fetchAll() {
            this.activeFrom = this.dateFrom;
            this.activeTo = this.dateTo;
            Object.keys(this.sections).forEach(s => this.fetchSection(s));
        },

        resetPeriod() {
            this.dateFrom = '';
            this.dateTo = '';
            this.fetchAll();
        },

        get hasFilter() { return this.activeFrom || this.activeTo; },

        s(name) { return this.sections[name]?.data; },
        sl(name) { return this.sections[name]?.loading; },

        formatSize(bytes) {
            if (!bytes) return '-';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0, size = bytes;
            for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
            return size.toFixed(1) + ' ' + units[i];
        },
        formatDate(iso) {
            if (!iso) return '-';
            return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        },
        timeAgo(iso) {
            if (!iso) return '';
            const s = Math.floor((Date.now() - new Date(iso)) / 1000);
            if (s < 60) return s + 's ago';
            const m = Math.floor(s / 60);
            if (m < 60) return m + 'm ago';
            const h = Math.floor(m / 60);
            if (h < 24) return h + 'h ago';
            return Math.floor(h / 24) + 'd ago';
        },
        maxCount(items) {
            if (!items || !items.length) return 1;
            return Math.max(...items.map(i => i.count));
        },
        pct(count, max) { return max > 0 ? Math.round((count / max) * 100) : 0; },
        formatHour(h) {
            if (h === 0) return '12 AM';
            if (h < 12) return h + ' AM';
            if (h === 12) return '12 PM';
            return (h - 12) + ' PM';
        },
        peakHour() {
            let items = this.s('hourly')?.hourly_activity;
            if (!items?.length) return '-';
            return this.formatHour(items.reduce((a, b) => a.count > b.count ? a : b).hour);
        },
        peakDay() {
            let items = this.s('weekday')?.weekday_activity;
            if (!items?.length) return '-';
            return this.weekdays[items.reduce((a, b) => a.count > b.count ? a : b).dow - 1] || '-';
        },
        fullHourly() {
            let map = {};
            (this.s('hourly')?.hourly_activity || []).forEach(h => map[h.hour] = h.count);
            let r = [];
            for (let i = 0; i < 24; i++) r.push({ hour: i, count: map[i] || 0 });
            return r;
        },
        fullWeekday() {
            let map = {};
            (this.s('weekday')?.weekday_activity || []).forEach(d => map[d.dow] = d.count);
            let r = [];
            for (let i = 1; i <= 7; i++) r.push({ dow: i, label: this.weekdays[i - 1], count: map[i] || 0 });
            return r;
        },
        userPct() {
            let d = this.s('system_user');
            let total = (d?.user_actions || 0) + (d?.system_actions || 0);
            return total > 0 ? Math.round((d.user_actions / total) * 100) : 0;
        },
        _esc(s) {
            let d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        },
        translateAttr(key) {
            let t = this.attrTranslations?.[key];
            if (t) {
                return `${this._esc(t)} <span class='text-gray-400 font-normal'>(${this._esc(key)})</span>`;
            }
            let h = key.replace(/[_\-.]/g, ' ').replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
            let extra = h !== key ? ` <span class='text-gray-400 font-normal font-mono text-xs'>${this._esc(key)}</span>` : '';
            return this._esc(h) + extra;
        }
    }">
        {{-- Period Filter --}}
        <div class="bg-white rounded-lg shadow px-4 py-3 mb-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-gray-700">{{ __('activitylog-browse::messages.stats_filter_period') }}</span>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500">{{ __('activitylog-browse::messages.from') }}</label>
                    <input type="date" x-model="dateFrom" class="rounded-md border-gray-300 shadow-sm text-sm px-2 py-1.5 border focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500">{{ __('activitylog-browse::messages.to') }}</label>
                    <input type="date" x-model="dateTo" class="rounded-md border-gray-300 shadow-sm text-sm px-2 py-1.5 border focus:border-blue-500 focus:ring-blue-500">
                </div>
                <button @click="fetchAll()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                    {{ __('activitylog-browse::messages.stats_apply') }}
                </button>
                <button x-show="hasFilter" @click="resetPeriod()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                    {{ __('activitylog-browse::messages.reset') }}
                </button>
                <template x-if="hasFilter">
                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span x-text="(activeFrom || '...') + ' → ' + (activeTo || '...')"></span>
                    </span>
                </template>
                <template x-if="!hasFilter">
                    <span class="text-xs text-gray-400">{{ __('activitylog-browse::messages.stats_all_time') }}</span>
                </template>
            </div>
        </div>

        {{-- Overview Cards --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_overview') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            @foreach([
                ['key' => 'total_rows', 'label' => 'stats_total_entries', 'format' => 'number'],
                ['key' => 'table_size', 'label' => 'stats_table_size', 'format' => 'size'],
                ['key' => 'avg_per_day', 'label' => 'stats_avg_per_day', 'format' => 'raw'],
                ['key' => 'oldest_entry', 'label' => 'stats_oldest_entry', 'format' => 'date'],
                ['key' => 'newest_entry', 'label' => 'stats_latest_entry', 'format' => 'date'],
            ] as $card)
                <div class="bg-white rounded-lg shadow px-4 py-3">
                    <div class="text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.' . $card['label']) }}</div>
                    <div class="mt-1 h-7 w-16 bg-gray-100 rounded animate-pulse" x-show="sl('overview')"></div>
                    <div class="mt-1 text-xl font-bold text-gray-900" x-show="!sl('overview')"
                        x-text="
                            @if($card['format'] === 'number') (s('overview')?.{{ $card['key'] }} ?? 0).toLocaleString()
                            @elseif($card['format'] === 'size') formatSize(s('overview')?.{{ $card['key'] }})
                            @elseif($card['format'] === 'date') formatDate(s('overview')?.{{ $card['key'] }})
                            @else s('overview')?.{{ $card['key'] }} ?? '-'
                            @endif
                        "></div>
                </div>
            @endforeach
        </div>

        {{-- Peak Hour Chart --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_peak_hour') }}</h2>
        <div class="bg-white rounded-lg shadow p-4 mb-8">
            <template x-if="sl('hourly')">
                <div class="h-48 flex items-center justify-center"><div class="h-6 w-32 bg-gray-100 rounded animate-pulse"></div></div>
            </template>
            <template x-if="!sl('hourly') && (!s('hourly')?.hourly_activity || s('hourly').hourly_activity.length === 0)">
                <div class="h-48 flex items-center justify-center text-sm text-gray-400">{{ __('activitylog-browse::messages.stats_no_data') }}</div>
            </template>
            <template x-if="!sl('hourly') && s('hourly')?.hourly_activity?.length > 0">
                <div>
                    <div class="mb-3 flex items-baseline gap-2">
                        <span class="text-sm text-gray-500">{{ __('activitylog-browse::messages.stats_busiest_hour') }}:</span>
                        <span class="text-lg font-bold text-orange-600" x-text="peakHour()"></span>
                        <span class="text-sm text-gray-400" x-text="'(' + s('hourly').hourly_activity.reduce((a, b) => a.count > b.count ? a : b).count.toLocaleString() + ' {{ __('activitylog-browse::messages.stats_entries') }})'"></span>
                    </div>
                    <div class="flex items-end gap-1 h-48">
                        <template x-for="(h, idx) in fullHourly()" :key="idx">
                            <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                    <span x-text="formatHour(h.hour)"></span>: <span x-text="h.count.toLocaleString()"></span>
                                </div>
                                <div class="w-full rounded-t transition-all"
                                    :class="h.count === maxCount(fullHourly()) && h.count > 0 ? 'bg-orange-500 hover:bg-orange-600' : 'bg-violet-400 hover:bg-violet-500'"
                                    :style="'height: ' + pct(h.count, maxCount(fullHourly())) + '%'"></div>
                            </div>
                        </template>
                    </div>
                    <div class="flex mt-2 text-xs text-gray-400">
                        <template x-for="(h, idx) in fullHourly()" :key="'hl-'+idx">
                            <span class="flex-1 text-center" x-text="h.hour % 3 === 0 ? formatHour(h.hour) : ''"></span>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Daily Activity --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_daily_activity') }}</h2>
        <div class="bg-white rounded-lg shadow p-4 mb-8">
            <template x-if="sl('daily')">
                <div class="h-48 flex items-center justify-center"><div class="h-6 w-32 bg-gray-100 rounded animate-pulse"></div></div>
            </template>
            <template x-if="!sl('daily') && (!s('daily')?.daily_activity || s('daily').daily_activity.length === 0)">
                <div class="h-48 flex items-center justify-center text-sm text-gray-400">{{ __('activitylog-browse::messages.stats_no_data') }}</div>
            </template>
            <template x-if="!sl('daily') && s('daily')?.daily_activity?.length > 0">
                <div>
                    <div class="flex items-end gap-1 h-48">
                        <template x-for="(day, idx) in s('daily').daily_activity" :key="idx">
                            <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                    <span x-text="day.date"></span>: <span x-text="day.count.toLocaleString()"></span>
                                </div>
                                <div class="w-full bg-blue-500 rounded-t transition-all hover:bg-blue-600" :style="'height: ' + pct(day.count, maxCount(s('daily').daily_activity)) + '%'"></div>
                            </div>
                        </template>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-400">
                        <span x-text="s('daily').daily_activity[0]?.date"></span>
                        <span x-text="s('daily').daily_activity[s('daily').daily_activity.length - 1]?.date"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Weekday Activity --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_weekday_activity') }}</h2>
        <div class="bg-white rounded-lg shadow p-4 mb-8">
            <template x-if="sl('weekday')">
                <div class="h-48 flex items-center justify-center"><div class="h-6 w-32 bg-gray-100 rounded animate-pulse"></div></div>
            </template>
            <template x-if="!sl('weekday') && (!s('weekday')?.weekday_activity || s('weekday').weekday_activity.length === 0)">
                <div class="h-48 flex items-center justify-center text-sm text-gray-400">{{ __('activitylog-browse::messages.stats_no_data') }}</div>
            </template>
            <template x-if="!sl('weekday') && s('weekday')?.weekday_activity?.length > 0">
                <div>
                    <div class="mb-3 flex items-baseline gap-2">
                        <span class="text-sm text-gray-500">{{ __('activitylog-browse::messages.stats_busiest_day') }}:</span>
                        <span class="text-lg font-bold text-orange-600" x-text="peakDay()"></span>
                    </div>
                    <div class="flex items-end gap-3 h-48">
                        <template x-for="(d, idx) in fullWeekday()" :key="idx">
                            <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                    <span x-text="d.label"></span>: <span x-text="d.count.toLocaleString()"></span>
                                </div>
                                <div class="w-full rounded-t transition-all"
                                    :class="d.count === maxCount(fullWeekday()) && d.count > 0 ? 'bg-orange-500 hover:bg-orange-600' : 'bg-teal-400 hover:bg-teal-500'"
                                    :style="'height: ' + pct(d.count, maxCount(fullWeekday())) + '%'"></div>
                            </div>
                        </template>
                    </div>
                    <div class="flex mt-2 text-xs text-gray-500 font-medium">
                        <template x-for="(d, idx) in fullWeekday()" :key="'wl-'+idx">
                            <span class="flex-1 text-center" x-text="d.label"></span>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Peak Times Summary --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_peak_times') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow px-4 py-4">
                <template x-if="sl('hourly')"><div class="space-y-2"><div class="h-4 w-24 bg-gray-100 rounded animate-pulse"></div><div class="h-8 w-16 bg-gray-100 rounded animate-pulse"></div></div></template>
                <template x-if="!sl('hourly')">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('activitylog-browse::messages.stats_busiest_hour') }}</div>
                        <div class="text-2xl font-bold text-orange-600" x-text="peakHour()"></div>
                        <template x-if="s('hourly')?.hourly_activity?.length > 0">
                            <div class="text-xs text-gray-400 mt-1" x-text="s('hourly').hourly_activity.reduce((a, b) => a.count > b.count ? a : b).count.toLocaleString() + ' {{ __('activitylog-browse::messages.stats_entries') }}'"></div>
                        </template>
                    </div>
                </template>
            </div>
            <div class="bg-white rounded-lg shadow px-4 py-4">
                <template x-if="sl('peak_day')"><div class="space-y-2"><div class="h-4 w-24 bg-gray-100 rounded animate-pulse"></div><div class="h-8 w-16 bg-gray-100 rounded animate-pulse"></div></div></template>
                <template x-if="!sl('peak_day')">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('activitylog-browse::messages.stats_busiest_day') }}</div>
                        <div class="text-2xl font-bold text-orange-600" x-text="s('peak_day')?.peak_day_date ?? '-'"></div>
                        <template x-if="s('peak_day')?.peak_day_count">
                            <div class="text-xs text-gray-400 mt-1" x-text="s('peak_day').peak_day_count.toLocaleString() + ' {{ __('activitylog-browse::messages.stats_entries') }}'"></div>
                        </template>
                    </div>
                </template>
            </div>
            <div class="bg-white rounded-lg shadow px-4 py-4">
                <template x-if="sl('monthly')"><div class="space-y-2"><div class="h-4 w-24 bg-gray-100 rounded animate-pulse"></div><div class="h-8 w-16 bg-gray-100 rounded animate-pulse"></div></div></template>
                <template x-if="!sl('monthly')">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('activitylog-browse::messages.stats_busiest_month') }}</div>
                        <div class="text-2xl font-bold text-orange-600" x-text="s('monthly')?.peak_month ?? '-'"></div>
                        <template x-if="s('monthly')?.peak_month_count">
                            <div class="text-xs text-gray-400 mt-1" x-text="s('monthly').peak_month_count.toLocaleString() + ' {{ __('activitylog-browse::messages.stats_entries') }}'"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Monthly Activity --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_monthly_activity') }}</h2>
        <div class="bg-white rounded-lg shadow p-4 mb-8">
            <template x-if="sl('monthly')">
                <div class="h-48 flex items-center justify-center"><div class="h-6 w-32 bg-gray-100 rounded animate-pulse"></div></div>
            </template>
            <template x-if="!sl('monthly') && (!s('monthly')?.monthly_activity || s('monthly').monthly_activity.length === 0)">
                <div class="h-48 flex items-center justify-center text-sm text-gray-400">{{ __('activitylog-browse::messages.stats_no_data') }}</div>
            </template>
            <template x-if="!sl('monthly') && s('monthly')?.monthly_activity?.length > 0">
                <div>
                    <div class="flex items-end gap-1 h-48">
                        <template x-for="(m, idx) in s('monthly').monthly_activity" :key="idx">
                            <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                    <span x-text="m.month"></span>: <span x-text="m.count.toLocaleString()"></span>
                                </div>
                                <div class="w-full rounded-t transition-all"
                                    :class="m.month === s('monthly').peak_month ? 'bg-orange-500 hover:bg-orange-600' : 'bg-cyan-500 hover:bg-cyan-600'"
                                    :style="'height: ' + pct(m.count, maxCount(s('monthly').monthly_activity)) + '%'"></div>
                            </div>
                        </template>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-400">
                        <span x-text="s('monthly').monthly_activity[0]?.month"></span>
                        <span x-text="s('monthly').monthly_activity[s('monthly').monthly_activity.length - 1]?.month"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- System vs User + Batch --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_system_vs_user') }}</h2>
                <div class="bg-white rounded-lg shadow p-4">
                    <template x-if="sl('system_user')">
                        <div class="h-24 flex items-center justify-center"><div class="h-6 w-32 bg-gray-100 rounded animate-pulse"></div></div>
                    </template>
                    <template x-if="!sl('system_user')">
                        <div>
                            <div class="flex gap-6 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                    <span class="text-sm text-gray-600">{{ __('activitylog-browse::messages.stats_user_actions') }}</span>
                                    <span class="text-sm font-bold text-gray-900" x-text="(s('system_user')?.user_actions || 0).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                                    <span class="text-sm text-gray-600">{{ __('activitylog-browse::messages.stats_system_actions') }}</span>
                                    <span class="text-sm font-bold text-gray-900" x-text="(s('system_user')?.system_actions || 0).toLocaleString()"></span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                                <div class="bg-blue-500 h-4 rounded-full transition-all" :style="'width: ' + userPct() + '%'"></div>
                            </div>
                            <div class="flex justify-between mt-1 text-xs text-gray-400">
                                <span x-text="userPct() + '% {{ __('activitylog-browse::messages.stats_user_actions') }}'"></span>
                                <span x-text="(100 - userPct()) + '% {{ __('activitylog-browse::messages.stats_system_actions') }}'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ __('activitylog-browse::messages.stats_batch_operations') }}</h2>
                <div class="bg-white rounded-lg shadow p-4">
                    <template x-if="sl('batches')">
                        <div class="h-24 flex items-center justify-center"><div class="h-6 w-32 bg-gray-100 rounded animate-pulse"></div></div>
                    </template>
                    <template x-if="!sl('batches')">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600" x-text="(s('batches')?.batch_count || 0).toLocaleString()"></div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('activitylog-browse::messages.stats_batch_count') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600" x-text="(s('batches')?.batched_entries || 0).toLocaleString()"></div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('activitylog-browse::messages.stats_batched_entries') }}</div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Breakdown Tables --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            {{-- Events --}}
            @include('activitylog-browse::partials.stats-table', [
                'sectionKey' => 'events',
                'dataKey' => 'event_counts',
                'title' => __('activitylog-browse::messages.stats_events_breakdown'),
                'colLabel' => __('activitylog-browse::messages.stats_event'),
                'itemKey' => 'event',
                'barColor' => 'bg-blue-500',
                'badge' => true,
            ])

            {{-- Log Names --}}
            @include('activitylog-browse::partials.stats-table', [
                'sectionKey' => 'log_names',
                'dataKey' => 'log_name_counts',
                'title' => __('activitylog-browse::messages.stats_log_names'),
                'colLabel' => __('activitylog-browse::messages.stats_log_name'),
                'itemKey' => 'log_name',
                'barColor' => 'bg-indigo-500',
            ])

            {{-- Top Models --}}
            @include('activitylog-browse::partials.stats-table', [
                'sectionKey' => 'models',
                'dataKey' => 'subject_type_counts',
                'title' => __('activitylog-browse::messages.stats_top_models'),
                'colLabel' => __('activitylog-browse::messages.stats_model'),
                'itemKey' => 'subject_type',
                'barColor' => 'bg-emerald-500',
            ])

            {{-- Top Causers --}}
            @include('activitylog-browse::partials.stats-table', [
                'sectionKey' => 'causers',
                'dataKey' => 'top_causers',
                'title' => __('activitylog-browse::messages.stats_top_causers'),
                'colLabel' => __('activitylog-browse::messages.stats_causer'),
                'itemKey' => 'causer',
                'barColor' => 'bg-amber-500',
            ])
        </div>

        {{-- Most Changed Attributes --}}
        @include('activitylog-browse::partials.stats-table', [
            'sectionKey' => 'attributes',
            'dataKey' => 'top_attributes',
            'title' => __('activitylog-browse::messages.stats_top_attributes'),
            'colLabel' => __('activitylog-browse::messages.stats_attribute'),
            'itemKey' => 'attribute',
            'barColor' => 'bg-rose-500',
            'fullWidth' => true,
            'mono' => true,
            'translate' => true,
        ])
    </div>
@endsection
