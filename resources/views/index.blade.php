@extends('activitylog-browse::layout')

@php
    $isRelatedMode = request('via_activity') && request('via_relation');
@endphp

@section('title', $isRelatedMode
    ? __('activitylog-browse::messages.related_logs_for', ['model' => class_basename(request('subject_type', '')) . ' — ' . Str::headline(request('via_relation')), 'relation' => Str::headline(request('via_relation'))])
    : __('activitylog-browse::messages.activity_log'))

@section('content')
    @if($isRelatedMode)
        <div class="mb-4 flex items-center gap-3 text-sm">
            <a href="{{ route('activitylog-browse.show', request('via_activity')) }}" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('activitylog-browse::messages.back_to_activity', ['id' => request('via_activity')]) }}
            </a>
        </div>
    @endif

    <div class="flex gap-6 items-start">
        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            @if($requestIdAlert ?? null)
                <div role="alert" class="mb-4 px-4 py-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800 flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="space-y-1 min-w-0">
                        <p class="font-semibold">{{ __('activitylog-browse::messages.request_id_missing_title') }}</p>
                        <p>{{ __('activitylog-browse::messages.request_id_missing_body') }}</p>
                        @if($requestIdAlert['failure'])
                            <p>{{ __('activitylog-browse::messages.request_id_last_failure', ['at' => $requestIdAlert['failure']['at']]) }}</p>
                            <pre dir="ltr" class="text-left text-xs bg-yellow-100 rounded p-2 whitespace-pre-wrap break-all">{{ $requestIdAlert['failure']['message'] }}</pre>
                        @endif
                        <p>
                            {{ __('activitylog-browse::messages.request_id_retry_hint') }}
                            <code dir="ltr" class="px-1.5 py-0.5 rounded bg-yellow-100 text-xs select-all">php artisan activitylog-browse:ensure-columns</code>
                        </p>
                    </div>
                </div>
            @endif
            @include('activitylog-browse::partials.filters')

            @if($isRelatedMode)
                <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    <h2 class="text-sm font-semibold text-blue-800">
                        {{ __('activitylog-browse::messages.related_logs_for', ['model' => class_basename(request('subject_type', '')), 'relation' => Str::headline(request('via_relation'))]) }}
                    </h2>
                </div>
            @endif
            @if(request()->filled('request_id'))
                <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    <h2 class="text-sm font-semibold text-blue-800">{{ __('activitylog-browse::messages.request_changes') }}</h2>
                    <span dir="ltr" class="text-xs font-mono text-blue-700 select-all">{{ request('request_id') }}</span>
                    <a href="{{ request()->fullUrlWithoutQuery(['request_id', 'page']) }}"
                       class="ms-auto text-blue-500 hover:text-blue-700" title="{{ __('activitylog-browse::messages.remove_filter') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                </div>
            @endif
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.id') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.date') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.log') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.event') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.description') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.subject') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.causer') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.request') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($activities as $act)
                                @include('activitylog-browse::partials.activity-row', ['activity' => $act])
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('activitylog-browse::messages.no_activity_logs') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($activities->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $activities->links() }}
                    </div>
                @endif
            </div>

            <div class="mt-4 text-sm text-gray-500">
                {{ __('activitylog-browse::messages.showing_entries', ['first' => $activities->firstItem() ?? 0, 'last' => $activities->lastItem() ?? 0, 'total' => $activities->total()]) }}
            </div>
        </div>

        {{-- Model Info sidebar --}}
        <div id="model_info_card" style="display:none" class="w-80 shrink-0 sticky top-0 h-screen overflow-y-auto bg-white rounded-lg shadow p-4"
             x-data="{
                loading: false,
                info: null,
                search: '',
                selectedAttrs: @js(array_filter(explode(',', request('changed_attribute', '')))),
                formatSize(bytes) {
                    if (!bytes) return '-';
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let i = 0, size = bytes;
                    for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
                    return size.toFixed(3) + ' ' + units[i];
                },
                isAttrSelected(key) {
                    return this.selectedAttrs.includes(key);
                },
                get filteredAttrs() {
                    if (!this.info) return [];
                    if (!this.search) return this.info.attributes;
                    let s = this.search.toLowerCase();
                    return this.info.attributes.filter(a => a.key.toLowerCase().includes(s) || a.label.toLowerCase().includes(s));
                }
             }">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-show="info" x-text="info?.stats?.model_basename"></span>
                    — {{ __('activitylog-browse::messages.model_info') }}
                </h3>
            </div>

            {{-- Loading --}}
            <div x-show="loading" class="flex justify-center py-6">
                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            <template x-if="!loading && info">
                <div>
                    {{-- Stats mini cards --}}
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.total_logs') }}</div>
                            <div class="text-sm font-bold text-gray-900" x-text="info.stats.total_logs?.toLocaleString()"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.unique_records') }}</div>
                            <div class="text-sm font-bold text-gray-900" x-text="info.stats.unique_subjects?.toLocaleString()"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.table_name') }}</div>
                            <div class="text-sm font-bold text-gray-900 font-mono" x-text="info.stats.table_name ?? '-'"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.stats_table_size') }}</div>
                            <div class="text-sm font-bold text-gray-900" x-text="formatSize(info.stats.table_size)"></div>
                        </div>
                    </div>

                    {{-- Events --}}
                    <div class="flex flex-wrap gap-1 mb-4">
                        <template x-for="(count, event) in info.stats.events" :key="event">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium"
                                  :class="{
                                      'bg-green-100 text-green-800': event === 'created',
                                      'bg-blue-100 text-blue-800': event === 'updated',
                                      'bg-red-100 text-red-800': event === 'deleted',
                                      'bg-gray-100 text-gray-800': !['created','updated','deleted'].includes(event)
                                  }">
                                <span x-text="event"></span>
                                <span class="opacity-70" x-text="count"></span>
                            </span>
                        </template>
                    </div>

                    {{-- Attributes grid --}}
                    <div>
                        <template x-if="info && info.attributes.length > 8">
                            <input type="text" x-model="search"
                                   placeholder="{{ __('activitylog-browse::messages.search') }}..."
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-1.5 border focus:border-blue-500 focus:ring-blue-500 mb-3">
                        </template>
                        <div class="text-xs font-medium text-gray-500 uppercase mb-2">
                            {{ __('activitylog-browse::messages.model_attributes') }}
                            <span class="text-gray-400 normal-case" x-text="'(' + info.attributes.length + ')'"></span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="attr in filteredAttrs" :key="attr.key">
                                <button type="button"
                                        @click="toggleAttribute(attr.key)"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border cursor-pointer transition-colors"
                                        :class="isAttrSelected(attr.key)
                                            ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                                            : 'bg-white border-gray-200 text-gray-700 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700'"
                                        :title="'{{ __('activitylog-browse::messages.click_to_filter') }}'">
                                    <svg x-show="isAttrSelected(attr.key)" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="font-medium" x-text="attr.label"></span>
                                    <span class="opacity-70" x-show="attr.has_translation" x-text="'(' + attr.key + ')'"></span>
                                    <span x-show="!attr.has_translation" class="font-mono opacity-70" x-text="attr.key === attr.label ? '' : attr.key"></span>
                                </button>
                            </template>
                            <template x-if="filteredAttrs.length === 0 && search">
                                <span class="text-sm text-gray-400 italic py-1">{{ __('activitylog-browse::messages.no_data') }}</span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{--
        Shared details dialog, opened from row icons via $dispatch('open-details', payload):
          {type: 'request'|'changes'|'attributes', id, title, url, emptyText?}
        Data is fetched on open so rows stay light.
    --}}
    <div x-data="{
            open: false,
            seq: 0,
            payload: null,
            details: null,
            loading: false,
            failed: false,
            search: '',
            hideEmpty: false,
            copied: null,
            filterTitle: @js(__('activitylog-browse::messages.filter_by_value')),
            // Clicking a value adds it to the current filters (back to page 1).
            filterUrl(filter) {
                let url = new URL(window.location.href);
                url.searchParams.set(filter.key, filter.value);
                url.searchParams.delete('page');
                return url.toString();
            },
            show(payload) {
                // A counter, not a payload comparison: Alpine stores payload behind a reactive proxy.
                let seq = ++this.seq;
                this.payload = payload;
                this.details = null;
                this.failed = false;
                this.loading = true;
                this.search = payload.type === 'request' ? @js((string) request('body_search', '')) : '';
                this.hideEmpty = false;
                this.copied = null;
                this.open = true;
                document.body.classList.add('overflow-hidden');
                this.$nextTick(() => this.$refs.search.focus());
                fetch(payload.url, { headers: { Accept: 'application/json' } })
                    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                    .then(data => { if (seq === this.seq) this.details = this.normalize(payload, data); })
                    .catch(e => { console.error('activitylog-browse: loading details failed', e); if (seq === this.seq) this.failed = true; })
                    .finally(() => { if (seq === this.seq) this.loading = false; });
            },
            close() {
                this.open = false;
                document.body.classList.remove('overflow-hidden');
            },
            normalize(payload, data) {
                if (payload.type === 'changes') {
                    return { sections: data.rows.length ? [{
                        title: '', diff: true, hasOld: data.has_old, hasNew: data.has_new,
                        rows: data.rows.map(r => ({ ...r, empty: this.blank(r.old) && this.blank(r.new) })),
                    }] : [] };
                }
                if (payload.type === 'attributes') {
                    return { sections: data.rows.length ? [{ title: '', rows: data.rows.map(r => ({
                        ...r,
                        kind: this.blank(r.value) ? 'null' : 'mono',
                        empty: this.blank(r.value) || ['0', 'false'].includes(r.value),
                    })) }] : [] };
                }
                return data;
            },
            blank(v) { return v === null || v === ''; },
            isEmpty(row) { return row.empty ?? row.kind === 'null'; },
            copy(section) {
                if (!navigator.clipboard) return;
                navigator.clipboard.writeText(section.copy).then(() => {
                    this.copied = section.title;
                    setTimeout(() => this.copied = null, 1500);
                });
            },
            get sections() {
                if (!this.details) return [];
                let s = this.search.trim().toLowerCase();
                return this.details.sections
                    .map(sec => ({ ...sec, rows: sec.rows.filter(r =>
                        (!this.hideEmpty || !this.isEmpty(r))
                        && (!s || [r.label, r.key, r.value, r.display, r.old, r.new, r.old_display, r.new_display].join(' ').toLowerCase().includes(s))
                    ) }))
                    .filter(sec => sec.rows.length);
            }
         }"
         @open-details.window="show($event.detail)"
         @keydown.escape.window="open && close()"
         x-show="open" style="display:none"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/50" @click="close()"></div>
        <div role="dialog" aria-modal="true" x-show="open" x-transition
             class="relative w-full max-w-4xl max-h-[85vh] flex flex-col bg-white rounded-lg shadow-xl border border-gray-200">
            <div class="flex flex-wrap items-center gap-3 px-5 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900" x-text="payload?.title"></h3>
                <label class="ms-auto flex items-center gap-1 text-xs text-gray-500 cursor-pointer whitespace-nowrap select-none">
                    <input type="checkbox" x-model="hideEmpty" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-3 w-3">
                    {{ __('activitylog-browse::messages.hide_empty') }}
                </label>
                <input x-ref="search" type="text" x-model="search"
                       placeholder="{{ __('activitylog-browse::messages.search') }}..."
                       class="w-64 rounded-md border-gray-300 text-sm px-3 py-1.5 border focus:border-blue-500 focus:ring-blue-500">
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 focus:outline-none"
                        title="{{ __('activitylog-browse::messages.close') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto px-5 py-4 space-y-5">
                <template x-if="loading">
                    <div class="flex justify-center py-8">
                        <svg class="animate-spin h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                </template>
                <template x-if="failed">
                    <p class="text-sm text-red-600">{{ __('activitylog-browse::messages.load_failed') }}</p>
                </template>
                <template x-if="details && details.request_id">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span>{{ __('activitylog-browse::messages.request_id') }}:</span>
                        <span dir="ltr" class="font-mono text-gray-700 select-all" x-text="details.request_id"></span>
                        <a :href="details.request_logs_url" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800">
                            {{ __('activitylog-browse::messages.request_changes') }}
                            <span class="tabular-nums" x-text="'(' + details.request_count + ')'"></span>
                        </a>
                    </div>
                </template>
                <template x-for="(section, sectionIndex) in sections" :key="sectionIndex + section.title">
                    <div>
                        <template x-if="section.title || section.copy">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-semibold text-gray-500 uppercase" x-text="section.title"></span>
                                <template x-if="section.note">
                                    <span class="text-xs text-gray-400" x-text="section.note"></span>
                                </template>
                                <template x-if="section.truncated">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">{{ __('activitylog-browse::messages.request_body_truncated') }}</span>
                                </template>
                                <template x-if="section.copy">
                                    <button type="button" @click="copy(section)"
                                            class="ms-auto inline-flex items-center gap-1 text-xs text-gray-500 hover:text-blue-600 focus:outline-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <span x-text="copied === section.title ? @js(__('activitylog-browse::messages.copied')) : @js(__('activitylog-browse::messages.copy_json'))"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <table class="w-full text-sm">
                            <template x-if="section.diff">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-start py-1.5 pe-3 text-xs font-medium text-gray-500 w-48">{{ __('activitylog-browse::messages.attr') }}</th>
                                        <th x-show="section.hasOld" class="text-start py-1.5 pe-3 text-xs font-medium text-gray-500">{{ __('activitylog-browse::messages.old') }}</th>
                                        <th x-show="section.hasNew" class="text-start py-1.5 text-xs font-medium text-gray-500">{{ __('activitylog-browse::messages.new') }}</th>
                                    </tr>
                                </thead>
                            </template>
                            <tbody>
                                <template x-for="row in section.rows" :key="row.key">
                                    <tr class="border-b border-gray-100">
                                        <td class="py-1.5 pe-3 font-medium text-gray-700 align-top whitespace-nowrap w-48" :title="row.key" x-text="row.label"></td>
                                        <td x-show="section.diff && section.hasOld" class="py-1.5 pe-3 align-top break-all" dir="auto"
                                            :class="row.old === null ? 'text-gray-400 italic' : 'text-red-700'">
                                            <template x-if="row.same_content"><span class="text-xs italic text-gray-500" :title="row.old">{{ __('activitylog-browse::messages.same_content_note') }}</span></template>
                                            <template x-if="!row.same_content && row.old_parts"><span><template x-for="(part, i) in row.old_parts" :key="i"><span :class="part[1] ? 'bg-red-100 rounded px-0.5' : 'text-gray-500'" x-text="part[0]"></span></template></span></template>
                                            <template x-if="!row.same_content && !row.old_parts && row.old_display"><span><span x-text="row.old_display"></span> <span class="text-xs text-gray-400 font-mono" x-text="row.old"></span></span></template>
                                            <template x-if="!row.same_content && !row.old_parts && !row.old_display"><span x-text="row.old ?? '-'"></span></template>
                                        </td>
                                        <td x-show="section.diff && section.hasNew" class="py-1.5 align-top break-all" dir="auto"
                                            :class="row.new === null ? 'text-gray-400 italic' : 'text-green-700'">
                                            <template x-if="row.same_content"><span class="text-xs italic text-gray-500" :title="row.new">{{ __('activitylog-browse::messages.same_content_note') }}</span></template>
                                            <template x-if="!row.same_content && row.new_parts"><span><template x-for="(part, i) in row.new_parts" :key="i"><span :class="part[1] ? 'bg-green-100 rounded px-0.5' : 'text-gray-500'" x-text="part[0]"></span></template></span></template>
                                            <template x-if="!row.same_content && !row.new_parts && row.new_display"><span><span x-text="row.new_display"></span> <span class="text-xs text-gray-400 font-mono" x-text="row.new"></span></span></template>
                                            <template x-if="!row.same_content && !row.new_parts && !row.new_display"><span x-text="row.new ?? '-'"></span></template>
                                        </td>
                                        <td x-show="!section.diff" class="py-1.5 text-gray-600 break-all">
                                            <template x-if="row.kind === 'null'">
                                                <span class="italic text-gray-400">{{ __('activitylog-browse::messages.null') }}</span>
                                            </template>
                                            <template x-if="row.kind === 'badge'">
                                                <a class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="[row.class, row.filter ? 'hover:underline' : '']"
                                                   :href="row.filter ? filterUrl(row.filter) : null" :data-tip="row.filter ? filterTitle : null" x-text="row.value"></a>
                                            </template>
                                            <template x-if="row.kind === 'text'">
                                                <a dir="auto" class="tabular-nums" :href="row.filter ? filterUrl(row.filter) : null" :data-tip="row.filter ? filterTitle : null" :class="row.filter ? 'cursor-pointer hover:text-blue-600 hover:underline' : ''" x-text="row.value"></a>
                                            </template>
                                            <template x-if="row.kind === 'mono' && row.display">
                                                <span><span dir="auto" x-text="row.display"></span> <span class="text-xs text-gray-400 font-mono" x-text="row.value"></span></span>
                                            </template>
                                            <template x-if="row.kind === 'mono' && !row.display">
                                                <a dir="auto" class="font-mono text-xs" :href="row.filter ? filterUrl(row.filter) : null" :data-tip="row.filter ? filterTitle : null" :class="row.filter ? 'cursor-pointer hover:text-blue-600 hover:underline' : ''" x-text="row.value"></a>
                                            </template>
                                            <template x-if="row.kind === 'pre'">
                                                <pre dir="ltr" class="text-left text-xs bg-gray-50 p-2 rounded max-h-80 overflow-auto whitespace-pre-wrap" x-text="row.value"></pre>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
                <template x-if="details && sections.length === 0">
                    <p class="text-sm text-gray-400 italic" x-text="(details.sections.length === 0 && payload?.emptyText) || @js(__('activitylog-browse::messages.no_data'))"></p>
                </template>
            </div>
        </div>
    </div>
@endsection
