@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.cleanup_title'))

@section('content')
    <script>
        /**
         * Preview → confirm → run in batches for the cleanup page's strip actions.
         * Each POST changes at most one batch, so a 300k-row cleanup never hits a request timeout.
         */
        function activitylogStripTask(config) {
            return {
                days: config.days ?? null,
                count: null, bytes: null, loading: false, failed: false,
                confirming: false, running: false, processed: 0, total: 0,
                async preview() {
                    this.loading = true;
                    this.failed = false;
                    try {
                        const res = await fetch(config.previewUrl(this), { headers: { Accept: 'application/json' } });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        const data = await res.json();
                        this.count = data.count;
                        this.bytes = data.bytes ?? null;
                    } catch (e) {
                        console.error('activitylog-browse: cleanup preview failed', e);
                        this.failed = true;
                        this.count = null;
                    }
                    this.loading = false;
                },
                async run() {
                    this.confirming = false;
                    this.running = true;
                    this.failed = false;
                    this.processed = 0;
                    this.total = this.count || 0;
                    try {
                        for (;;) {
                            const res = await fetch(config.runUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                                body: JSON.stringify({ days: this.days }),
                            });
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            const data = await res.json();
                            this.processed += data.processed;
                            if (data.done) break;
                        }
                    } catch (e) {
                        console.error('activitylog-browse: cleanup run failed', e);
                        this.failed = true;
                    }
                    this.running = false;
                    await this.preview();
                },
                get percent() {
                    return this.total ? Math.min(100, Math.round(this.processed / this.total * 100)) : 0;
                },
                formatBytes(bytes) {
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let i = 0, size = bytes;
                    for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
                    return '\u2066' + size.toFixed(1) + ' ' + units[i] + '\u2069'; // LTR isolate inside RTL text
                },
            };
        }
    </script>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('activitylog-browse.index') }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('activitylog-browse::messages.back_to_list') }}
        </a>
        <h1 class="ms-auto text-xl font-semibold text-gray-900">{{ __('activitylog-browse::messages.cleanup_title') }}</h1>
    </div>

    @if(session('success'))
        <div role="status" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Overview Cards --}}
    <div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-4" x-data="{
        formatSize(bytes) {
            if (!bytes) return '—';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0, size = bytes;
            for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
            return '\u2066' + size.toFixed(3) + ' ' + units[i] + '\u2069';
        }
    }">
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_total_rows'), 'value' => number_format($totalRows)])
        {{-- Table size: freed space only shows after a rebuild (OPTIMIZE TABLE), which this card can start now --}}
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3"
             x-data="{
                size: {{ (int) $tableSize }},
                before: null,
                running: false,
                failed: null,
                pending: @js($optimizePendingSince),
                polls: 0,
                async reclaim() {
                    this.before = this.size;
                    this.failed = null;
                    this.running = true;
                    try {
                        const res = await fetch(@js(route('activitylog-browse.cleanup-reclaim-space')), {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        this.polls = 0;
                        setTimeout(() => this.poll(), 2000);
                    } catch (e) {
                        console.error('activitylog-browse: reclaim space failed', e);
                        this.running = false;
                        this.failed = @js(__('activitylog-browse::messages.reclaim_failed'));
                    }
                },
                async poll() {
                    // ~20 minutes at most; a rebuild normally takes seconds to a few minutes.
                    if (++this.polls > 400) {
                        this.running = false;
                        this.failed = @js(__('activitylog-browse::messages.reclaim_still_running'));
                        return;
                    }
                    try {
                        const res = await fetch(@js(route('activitylog-browse.cleanup-table-size')), { headers: { Accept: 'application/json' } });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        const data = await res.json();
                        if (data.running) {
                            setTimeout(() => this.poll(), 3000);
                            return;
                        }
                        this.running = false;
                        this.pending = data.pending_since;
                        if (data.error) {
                            this.failed = data.error;
                        } else if (data.size) {
                            this.size = data.size;
                        }
                    } catch (e) {
                        console.error('activitylog-browse: table size refresh failed', e);
                        setTimeout(() => this.poll(), 5000);
                    }
                },
                get saved() {
                    return this.before !== null && this.before > this.size ? this.before - this.size : null;
                },
                get savedPercent() {
                    // LTR isolate so the percentage keeps its order inside RTL text.
                    return this.saved ? '\u2066(−' + (this.saved / this.before * 100).toFixed(1) + '%)\u2069' : '';
                },
                formatSize(bytes) {
                    if (!bytes) return '—';
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let i = 0, size = bytes;
                    for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
                    return '\u2066' + size.toFixed(1) + ' ' + units[i] + '\u2069'; // LTR isolate inside RTL text
                }
             }"
             x-init="{{ $rebuildRunning ? 'running = true; poll()' : '' }}">
            <div class="flex items-center justify-between gap-2">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">{{ __('activitylog-browse::messages.cleanup_table_size') }}</div>
                <button data-tip="{{ __('activitylog-browse::messages.reclaim_now') }}" data-tip-align="end" type="button" @click="reclaim()" :disabled="running"
                        class="min-w-0 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" :class="running ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span class="truncate">{{ __('activitylog-browse::messages.reclaim_now') }}</span>
                </button>
            </div>
            <div class="mt-1 text-2xl font-bold text-gray-900 tabular-nums" x-text="formatSize(size)"></div>
            <template x-if="saved">
                <p class="mt-1 text-xs font-medium text-green-700">{{ __('activitylog-browse::messages.reclaim_saved') }} <span class="tabular-nums" x-text="formatSize(saved)"></span> <span class="tabular-nums" x-text="savedPercent"></span></p>
            </template>
            <template x-if="running">
                <p class="mt-1 text-xs text-blue-700">{{ __('activitylog-browse::messages.reclaim_running') }}</p>
            </template>
            <template x-if="failed">
                <p class="mt-1 text-xs text-red-600" x-text="failed"></p>
            </template>
            <template x-if="!running && !saved && pending">
                <p class="mt-1 text-xs text-amber-700">{{ __('activitylog-browse::messages.reclaim_pending') }}</p>
            </template>
        </div>
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_oldest_entry'), 'value' => $oldestEntry ? $oldestEntry->format('Y-m-d') : '—', 'size' => 'text-sm'])
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_newest_entry'), 'value' => $newestEntry ? $newestEntry->format('Y-m-d') : '—', 'size' => 'text-sm'])
    </div>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
        {{-- Actions --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Delete old activities --}}
            <section class="bg-white rounded-lg shadow">
            <div class="px-5 py-4 border-b border-gray-200 flex items-start gap-3">
                <span class="shrink-0 w-9 h-9 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('activitylog-browse::messages.cleanup_delete_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('activitylog-browse::messages.cleanup_delete_hint') }}</p>
                </div>
            </div>
                <div class="p-5">
            <div x-data="{
        days: '',
        models: @js($models),
        selectedModels: [],
        modelSearch: '',
        modelDropdownOpen: false,
        previewCount: null,
        previewLoading: false,
        confirmModal: false,
        confirmMessage: '',
        submitting: false,

        get filteredModels() {
            if (!this.modelSearch) return this.models;
            const s = this.modelSearch.toLowerCase();
            return this.models.filter(m => m.label.toLowerCase().includes(s) || m.value.toLowerCase().includes(s));
        },

        toggleModel(value) {
            const idx = this.selectedModels.indexOf(value);
            if (idx > -1) {
                this.selectedModels.splice(idx, 1);
            } else {
                this.selectedModels.push(value);
            }
            this.fetchPreview();
        },

        isSelected(value) {
            return this.selectedModels.includes(value);
        },

        selectAll() {
            this.selectedModels = this.models.map(m => m.value);
            this.fetchPreview();
        },

        clearSelection() {
            this.selectedModels = [];
            this.fetchPreview();
        },

        get parsedDays() {
            return this.days === '' || this.days === null ? NaN : parseInt(this.days);
        },

        async fetchPreview() {
            if (isNaN(this.parsedDays) || this.parsedDays < 0) {
                this.previewCount = null;
                return;
            }
            this.previewLoading = true;
            try {
                const params = new URLSearchParams();
                params.append('days', this.days);
                this.selectedModels.forEach(m => params.append('models[]', m));
                const res = await fetch(`{{ route('activitylog-browse.cleanup-preview') }}?${params.toString()}`);
                const data = await res.json();
                this.previewCount = data.count;
            } catch (e) {
                this.previewCount = null;
            }
            this.previewLoading = false;
        },

        showConfirm() {
            if (isNaN(this.parsedDays) || this.parsedDays < 0 || this.previewCount === null || this.previewCount === 0) return;
            this.confirmMessage = this.parsedDays === 0
                ? '{{ __('activitylog-browse::messages.confirm_delete_all', ['count' => '__COUNT__']) }}'.replace('__COUNT__', this.previewCount)
                : '{{ __('activitylog-browse::messages.confirm_delete', ['count' => '__COUNT__', 'days' => '__DAYS__']) }}'.replace('__COUNT__', this.previewCount).replace('__DAYS__', this.days);
            this.confirmModal = true;
        },

        submitAction() {
            this.submitting = true;
            this.$refs.deleteForm.submit();
        }
    }" x-init="$watch('days', () => fetchPreview())">

                <div>

                    {{-- Days Input --}}
                    <div class="mb-6">
                        <label for="days" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('activitylog-browse::messages.days_older_than') }}
                        </label>
                        <input type="number" id="days" x-model="days" min="0" step="1"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                               placeholder="30">
                    </div>

                    {{-- Model Multi-Select --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('activitylog-browse::messages.select_models') }}
                        </label>
                        <div class="relative" @click.away="modelDropdownOpen = false">
                            <button type="button" @click="modelDropdownOpen = !modelDropdownOpen"
                                    class="w-full flex items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm hover:bg-gray-50 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <span x-text="selectedModels.length === 0 ? '{{ __('activitylog-browse::messages.all_models') }}' : selectedModels.length + ' {{ __('activitylog-browse::messages.model') }}(s)'"
                                  class="text-gray-700"></span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="modelDropdownOpen" x-transition
                                 class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg max-h-60 overflow-hidden">
                                <div class="p-2 border-b border-gray-100">
                                    <input type="text" x-model="modelSearch"
                                           class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                           placeholder="{{ __('activitylog-browse::messages.search_models') }}">
                                </div>
                                <div class="p-2 border-b border-gray-100 flex gap-2">
                                    <button type="button" @click="selectAll()"
                                            class="text-xs text-blue-600 hover:text-blue-800">{{ __('activitylog-browse::messages.all') }}</button>
                                    <button type="button" @click="clearSelection()"
                                            class="text-xs text-gray-500 hover:text-gray-700">{{ __('activitylog-browse::messages.reset') }}</button>
                                </div>
                                <div class="overflow-y-auto max-h-44">
                                    <template x-for="model in filteredModels" :key="model.value">
                                        <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer text-sm">
                                            <input type="checkbox" :checked="isSelected(model.value)"
                                                   @change="toggleModel(model.value)"
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span x-text="model.label" class="text-gray-700"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <p x-show="selectedModels.length === 0" class="mt-1 text-xs text-gray-500">
                            {{ __('activitylog-browse::messages.no_models_selected') }}
                        </p>
                    </div>

                    {{-- Preview Count --}}
                    <div class="mb-6 rounded-lg border p-4"
                         :class="previewCount !== null && previewCount > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50'">
                        <template x-if="previewLoading">
                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ __('activitylog-browse::messages.loading') }}...
                            </div>
                        </template>
                        <template x-if="!previewLoading && previewCount !== null && previewCount > 0">
                            <div class="text-sm font-medium text-amber-800"
                                 x-text="'{{ __('activitylog-browse::messages.cleanup_preview_count', ['count' => '__COUNT__']) }}'.replace('__COUNT__', previewCount)">
                            </div>
                        </template>
                        <template x-if="!previewLoading && previewCount !== null && previewCount === 0">
                            <div class="text-sm text-gray-500">{{ __('activitylog-browse::messages.cleanup_no_match') }}</div>
                        </template>
                        <template x-if="!previewLoading && previewCount === null">
                            <div class="text-sm text-gray-500">{{ __('activitylog-browse::messages.cleanup_enter_days') }}</div>
                        </template>
                    </div>

                    {{-- Action Button --}}
                    <div class="flex gap-3">
                        <button type="button" @click="showConfirm()"
                                :disabled="isNaN(parsedDays) || parsedDays < 0 || previewCount === null || previewCount === 0"
                                class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('activitylog-browse::messages.delete_logs') }}
                        </button>
                    </div>

                    {{-- Hidden Forms --}}
                    <form x-ref="deleteForm" method="POST" action="{{ route('activitylog-browse.cleanup-delete') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="days" :value="days">
                        <template x-for="m in selectedModels" :key="m">
                            <input type="hidden" name="models[]" :value="m">
                        </template>
                    </form>

                    {{-- Confirmation Modal --}}
                    <div x-show="confirmModal" x-transition
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                         @keydown.escape.window="confirmModal = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6"
                             @click.away="confirmModal = false">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600"
                                         fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.delete_logs') }}</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-6" x-text="confirmMessage"></p>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="confirmModal = false" :disabled="submitting"
                                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    {{ __('activitylog-browse::messages.cancel') }}
                                </button>
                                <button type="button" @click="submitAction()" :disabled="submitting"
                                        class="rounded-md px-4 py-2 text-sm font-medium text-white shadow-sm disabled:opacity-50 bg-red-600 hover:bg-red-700">
                                <span x-show="!submitting">{{ __('activitylog-browse::messages.delete_logs') }}</span>
                                    <span x-show="submitting" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25"
                                                                                                      cx="12" cy="12"
                                                                                                      r="10"
                                                                                                      stroke="currentColor"
                                                                                                      stroke-width="4"></circle><path
                                        class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('activitylog-browse::messages.loading') }}...
                        </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </section>

            {{-- Request bodies --}}
            @php
                $bodyRetentionOn = (bool) ($bodyRetention['enabled'] ?? false);
                $bodyRetentionDays = max(1, (int) ($bodyRetention['days'] ?? 30));
            @endphp
            <section class="bg-white rounded-lg shadow"
                     x-data="activitylogStripTask({
                         days: {{ $bodyRetentionDays }},
                         previewUrl: (task) => @js(route('activitylog-browse.cleanup-bodies-preview')) + '?days=' + encodeURIComponent(task.days || 0),
                         runUrl: @js(route('activitylog-browse.cleanup-bodies')),
                         csrf: @js(csrf_token()),
                     })"
                     x-init="preview(); $watch('days', () => preview())">
            <div class="px-5 py-4 border-b border-gray-200 flex items-start gap-3">
                <span class="shrink-0 w-9 h-9 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('activitylog-browse::messages.cleanup_bodies_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('activitylog-browse::messages.cleanup_bodies_hint') }}</p>
                </div>
                <span class="ms-auto shrink-0 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $bodyRetentionOn ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $bodyRetentionOn ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                    {{ $bodyRetentionOn ? __('activitylog-browse::messages.retention_enabled') : __('activitylog-browse::messages.retention_disabled_label') }}
                </span>
            </div>
                <div class="p-5 space-y-4">
                    <p class="text-xs text-gray-500">
                        {{ $bodyRetentionOn ? __('activitylog-browse::messages.body_retention_auto', ['days' => $bodyRetentionDays]) : __('activitylog-browse::messages.body_retention_off') }}
                    </p>
                    <div class="max-w-xs">
                        <label for="body_days" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.days_older_than') }}</label>
                        <input type="number" id="body_days" x-model.number.debounce.400ms="days" min="0" step="1"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 tabular-nums">
                    </div>

                <div class="rounded-lg border p-4 text-sm" :class="count > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50'">
                    <template x-if="loading"><span class="text-gray-500">{{ __('activitylog-browse::messages.loading') }}...</span></template>
                    <template x-if="!loading && failed"><span class="text-red-600">{{ __('activitylog-browse::messages.load_failed') }}</span></template>
                    <template x-if="!loading && !failed && count === 0"><span class="text-green-700">{{ __('activitylog-browse::messages.no_bodies_to_strip') }}</span></template>
                    <template x-if="!loading && !failed && count > 0">
                        <span class="text-amber-800">
                            {{ __('activitylog-browse::messages.matching_rows') }}: <strong class="tabular-nums" x-text="count.toLocaleString()"></strong>
                            <template x-if="bytes"><span class="text-amber-700"> · <span class="tabular-nums" x-text="formatBytes(bytes)"></span></span></template>
                        </span>
                    </template>
                </div>

                <template x-if="running || processed > 0">
                    <div>
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span x-text="running ? @js(__('activitylog-browse::messages.strip_progress')) : @js(__('activitylog-browse::messages.strip_done'))"></span>
                            <span class="tabular-nums" x-text="processed.toLocaleString() + ' / ' + total.toLocaleString()"></span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-blue-600 transition-all" :style="'width:' + percent + '%'"></div>
                        </div>
                    </div>
                </template>
                <template x-if="!running && processed > 0">
                    <p class="text-xs text-gray-500">{{ $autoOptimize ? __('activitylog-browse::messages.optimize_scheduled') : __('activitylog-browse::messages.optimize_manual') }}</p>
                </template>

                <div class="flex flex-wrap items-center gap-2">
                    <template x-if="!confirming">
                        <button type="button" @click="confirming = true" :disabled="loading || running || !(count > 0)"
                                class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('activitylog-browse::messages.strip_bodies_btn') }}
                        </button>
                    </template>
                    <template x-if="confirming">
                        <div class="flex flex-wrap items-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2">
                            <span class="text-sm text-red-800" x-text="@js(__('activitylog-browse::messages.confirm_strip', ['count' => '__COUNT__'])).replace('__COUNT__', (count || 0).toLocaleString())"></span>
                            <button type="button" @click="run()" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">{{ __('activitylog-browse::messages.confirm') }}</button>
                            <button type="button" @click="confirming = false" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('activitylog-browse::messages.cancel') }}</button>
                        </div>
                    </template>
                </div>
                </div>
            </section>

            {{-- Placeholder request/device data on job rows --}}
            <section class="bg-white rounded-lg shadow"
                     x-data="activitylogStripTask({
                         previewUrl: () => @js(route('activitylog-browse.cleanup-placeholder-preview')),
                         runUrl: @js(route('activitylog-browse.cleanup-placeholder')),
                         csrf: @js(csrf_token()),
                     })"
                     x-init="preview()">
            <div class="px-5 py-4 border-b border-gray-200 flex items-start gap-3">
                <span class="shrink-0 w-9 h-9 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('activitylog-browse::messages.cleanup_placeholder_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('activitylog-browse::messages.cleanup_placeholder_hint') }}</p>
                </div>
            </div>
                <div class="p-5 space-y-4">

                <div class="rounded-lg border p-4 text-sm" :class="count > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50'">
                    <template x-if="loading"><span class="text-gray-500">{{ __('activitylog-browse::messages.loading') }}...</span></template>
                    <template x-if="!loading && failed"><span class="text-red-600">{{ __('activitylog-browse::messages.load_failed') }}</span></template>
                    <template x-if="!loading && !failed && count === 0"><span class="text-green-700">{{ __('activitylog-browse::messages.placeholder_clean') }}</span></template>
                    <template x-if="!loading && !failed && count > 0">
                        <span class="text-amber-800">
                            {{ __('activitylog-browse::messages.matching_rows') }}: <strong class="tabular-nums" x-text="count.toLocaleString()"></strong>
                            <template x-if="bytes"><span class="text-amber-700"> · <span class="tabular-nums" x-text="formatBytes(bytes)"></span></span></template>
                        </span>
                    </template>
                </div>

                <template x-if="running || processed > 0">
                    <div>
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span x-text="running ? @js(__('activitylog-browse::messages.strip_progress')) : @js(__('activitylog-browse::messages.strip_done'))"></span>
                            <span class="tabular-nums" x-text="processed.toLocaleString() + ' / ' + total.toLocaleString()"></span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-blue-600 transition-all" :style="'width:' + percent + '%'"></div>
                        </div>
                    </div>
                </template>
                <template x-if="!running && processed > 0">
                    <p class="text-xs text-gray-500">{{ $autoOptimize ? __('activitylog-browse::messages.optimize_scheduled') : __('activitylog-browse::messages.optimize_manual') }}</p>
                </template>

                <div class="flex flex-wrap items-center gap-2">
                    <template x-if="!confirming">
                        <button type="button" @click="confirming = true" :disabled="loading || running || !(count > 0)"
                                class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('activitylog-browse::messages.strip_placeholder_btn') }}
                        </button>
                    </template>
                    <template x-if="confirming">
                        <div class="flex flex-wrap items-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2">
                            <span class="text-sm text-red-800" x-text="@js(__('activitylog-browse::messages.confirm_strip', ['count' => '__COUNT__'])).replace('__COUNT__', (count || 0).toLocaleString())"></span>
                            <button type="button" @click="run()" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">{{ __('activitylog-browse::messages.confirm') }}</button>
                            <button type="button" @click="confirming = false" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('activitylog-browse::messages.cancel') }}</button>
                        </div>
                    </template>
                </div>
                </div>
            </section>
        </div>

        {{-- Status & settings --}}
        <aside class="space-y-6">
            {{-- request_id column --}}
            @php
                $requestIdEnabled = (bool) (config('activitylog-browse.request_data.fields.request_id') ?? true);
                $requestIdOk = $requestIdStatus['column'] && $requestIdStatus['index'] !== false;
            @endphp
            <section class="bg-white rounded-lg shadow">
            <div class="px-5 py-4 border-b border-gray-200 flex items-start gap-3">
                <span class="shrink-0 w-9 h-9 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('activitylog-browse::messages.request_id_card_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('activitylog-browse::messages.request_id_card_hint') }}</p>
                </div>
            </div>
                <div class="p-5 space-y-3 text-sm">
                    @foreach(['column' => 'column_label', 'index' => 'index_label'] as $part => $labelKey)
                        @php $state = $requestIdStatus[$part]; @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">{{ __("activitylog-browse::messages.{$labelKey}") }}</span>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $state === true ? 'bg-green-100 text-green-800' : ($state === false ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700') }}">
                                {{ $state === true ? __('activitylog-browse::messages.status_ok') : ($state === false ? __('activitylog-browse::messages.status_missing') : __('activitylog-browse::messages.status_unknown')) }}
                            </span>
                        </div>
                    @endforeach
                    @if(! $requestIdEnabled)
                        <p class="text-xs text-gray-500">{{ __('activitylog-browse::messages.request_id_disabled') }}</p>
                    @elseif($requestIdOk)
                        <p class="text-xs text-gray-500">{{ __('activitylog-browse::messages.request_id_ok_hint') }}</p>
                    @else
                        @if($requestIdStatus['failure'])
                            <div class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-800 space-y-1">
                                <p>{{ __('activitylog-browse::messages.request_id_last_failure', ['at' => $requestIdStatus['failure']['at']]) }}</p>
                                <pre dir="ltr" class="text-left whitespace-pre-wrap break-all">{{ $requestIdStatus['failure']['message'] }}</pre>
                            </div>
                        @endif
                        <p class="text-xs text-gray-500">{{ __('activitylog-browse::messages.request_id_retry_hint') }}</p>
                        <form method="POST" action="{{ route('activitylog-browse.cleanup-request-id') }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                <span x-show="!submitting">{{ __('activitylog-browse::messages.request_id_add_btn') }}</span>
                                <span x-show="submitting">{{ __('activitylog-browse::messages.loading') }}...</span>
                            </button>
                        </form>
                    @endif
                </div>
            </section>

    {{-- Retention Settings --}}
    @php
        $retentionEnabled = (bool) ($retention['enabled'] ?? false);
        $perModelRules = (array) ($retention['per_model'] ?? []);
        $perLogNameRules = (array) ($retention['per_log_name'] ?? []);
        $scheduleFreq = $retention['schedule'] ?? null;
        $scheduleTime = $retention['schedule_time'] ?? '03:00';
    @endphp
    <section class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">
                    {{ __('activitylog-browse::messages.retention_settings') }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('activitylog-browse::messages.retention_hint') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($retentionEnabled)
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        {{ __('activitylog-browse::messages.retention_enabled') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        {{ __('activitylog-browse::messages.retention_disabled_label') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">{{ __('activitylog-browse::messages.retention_default_days') }}</div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ (int) ($retention['default_days'] ?? 0) }}
                    <span class="text-xs font-normal text-gray-500">{{ __('activitylog-browse::messages.days') }}</span>
                </div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">{{ __('activitylog-browse::messages.retention_max_rows') }}</div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ ($retention['max_rows'] ?? null) === null ? '—' : number_format((int) $retention['max_rows']) }}
                </div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">{{ __('activitylog-browse::messages.retention_max_size') }}</div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ ($retention['max_size_mb'] ?? null) === null ? '—' : ((int) $retention['max_size_mb']) . ' MB' }}
                </div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">{{ __('activitylog-browse::messages.retention_schedule') }}</div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ $scheduleFreq ? __('activitylog-browse::messages.retention_schedule_' . $scheduleFreq) : '—' }}
                    @if($scheduleFreq)
                        <span class="text-xs font-mono font-normal text-gray-500 ms-1">@ {{ $scheduleTime }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if(!empty($perModelRules) || !empty($perLogNameRules))
            <div class="grid gap-4 mb-4">
                @if(!empty($perModelRules))
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">
                            {{ __('activitylog-browse::messages.retention_per_model') }}
                        </h3>
                        <ul class="rounded border border-gray-200 divide-y divide-gray-100">
                            @foreach($perModelRules as $modelClass => $rule)
                                <li class="flex items-center justify-between px-3 py-2 text-sm">
                                    <span class="text-gray-700 truncate" title="{{ $modelClass }}">{{ class_basename($modelClass) }}</span>
                                    @if(is_string($rule) && strtolower($rule) === 'forever')
                                        <span class="rounded-full bg-purple-100 text-purple-800 px-2 py-0.5 text-xs font-medium">
                                            {{ __('activitylog-browse::messages.retention_forever') }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 text-xs">{{ (int) $rule }} {{ __('activitylog-browse::messages.days') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($perLogNameRules))
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">
                            {{ __('activitylog-browse::messages.retention_per_log_name') }}
                        </h3>
                        <ul class="rounded border border-gray-200 divide-y divide-gray-100">
                            @foreach($perLogNameRules as $logName => $rule)
                                <li class="flex items-center justify-between px-3 py-2 text-sm">
                                    <span class="text-gray-700">{{ $logName }}</span>
                                    @if(is_string($rule) && strtolower($rule) === 'forever')
                                        <span class="rounded-full bg-purple-100 text-purple-800 px-2 py-0.5 text-xs font-medium">
                                            {{ __('activitylog-browse::messages.retention_forever') }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 text-xs">{{ (int) $rule }} {{ __('activitylog-browse::messages.days') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('activitylog-browse.cleanup-retention') }}"
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf
            <button type="submit"
                    :disabled="submitting || !{{ $retentionEnabled ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <svg x-show="!submitting" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg x-show="submitting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-show="!submitting">{{ __('activitylog-browse::messages.run_retention_now') }}</span>
                <span x-show="submitting">{{ __('activitylog-browse::messages.loading') }}...</span>
            </button>
            @if(!$retentionEnabled)
                <p class="mt-2 text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_enable_in_config') }}</p>
            @endif
        </form>
    </section>


            {{-- Top Models --}}
            <div>
                @include('activitylog-browse::partials.stats-ajax-section', [
                    'sectionKey' => 'models',
                    'dataKey' => 'subject_type_counts',
                    'title' => __('activitylog-browse::messages.stats_top_models'),
                    'colLabel' => __('activitylog-browse::messages.stats_model'),
                    'itemKey' => 'subject_type',
                    'barColor' => 'bg-emerald-500',
                ])
            </div>
        </aside>
    </div>
@endsection
