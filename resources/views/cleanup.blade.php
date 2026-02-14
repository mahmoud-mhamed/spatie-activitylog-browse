@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.cleanup_title'))

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('activitylog-browse.index') }}"
           class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('activitylog-browse::messages.back_to_list') }}
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Overview Cards --}}
    <div class="mb-8 grid grid-cols-2 sm:grid-cols-4 gap-4" x-data="{
        formatSize(bytes) {
            if (!bytes) return '—';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0, size = bytes;
            for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
            return size.toFixed(3) + ' ' + units[i];
        }
    }">
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_total_rows'), 'value' => number_format($totalRows)])
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_table_size'), 'alpine' => 'formatSize(' . ((int) $tableSize) . ')'])
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_oldest_entry'), 'value' => $oldestEntry ? $oldestEntry->format('Y-m-d') : '—', 'size' => 'text-sm'])
        @include('activitylog-browse::partials.stat-card', ['label' => __('activitylog-browse::messages.cleanup_newest_entry'), 'value' => $newestEntry ? $newestEntry->format('Y-m-d') : '—', 'size' => 'text-sm'])
    </div>

    <section class="grid md:grid-cols-2 gap-4">
        <section>
            <div x-data="{
        days: '',
        models: @js($models),
        selectedModels: [],
        modelSearch: '',
        modelDropdownOpen: false,
        previewCount: null,
        previewLoading: false,
        confirmModal: false,
        confirmAction: '',
        confirmMessage: '',
        submitting: false,
        stripping: false,
        stripTotal: 0,
        stripProcessed: 0,
        stripDone: false,
        stripError: '',
        stripAutoRetry: false,
        stripRetryCount: 0,

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

        async fetchPreview() {
            if (!this.days || this.days < 1) {
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

        showConfirm(action) {
            if (!this.days || this.days < 1 || this.previewCount === null || this.previewCount === 0) return;
            this.confirmAction = action;
            if (action === 'delete') {
                this.confirmMessage = '{{ __('activitylog-browse::messages.confirm_delete', ['count' => '__COUNT__', 'days' => '__DAYS__']) }}'
                    .replace('__COUNT__', this.previewCount).replace('__DAYS__', this.days);
            } else {
                this.confirmMessage = '{{ __('activitylog-browse::messages.confirm_strip', ['count' => '__COUNT__', 'days' => '__DAYS__']) }}'
                    .replace('__COUNT__', this.previewCount).replace('__DAYS__', this.days);
            }
            this.confirmModal = true;
        },

        submitAction() {
            this.submitting = true;
            if (this.confirmAction === 'delete') {
                this.$refs.deleteForm.submit();
            } else {
                this.confirmModal = false;
                this.submitting = false;
                this.startStrip();
            }
        },

        async startStrip() {
            this.stripping = true;
            if (!this.stripError) {
                this.stripTotal = this.previewCount;
                this.stripProcessed = 0;
            }
            this.stripDone = false;
            this.stripError = '';
            this.stripRetryCount = 0;

            const params = new URLSearchParams();
            params.append('days', this.days);
            params.append('_token', '{{ csrf_token() }}');
            this.selectedModels.forEach(m => params.append('models[]', m));

            while (true) {
                try {
                    const res = await fetch('{{ route("activitylog-browse.cleanup-strip-batch") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: params.toString()
                    });
                    if (!res.ok) {
                        const errorData = await res.json().catch(() => null);
                        throw new Error(errorData?.message || `HTTP ${res.status}`);
                    }
                    const data = await res.json();
                    this.stripProcessed += data.processed;
                    this.stripRetryCount = 0;
                    this.stripError = '';

                    if (data.done) {
                        this.stripDone = true;
                        this.stripping = false;
                        this.previewCount = 0;
                        break;
                    }
                } catch (e) {
                    this.stripError = e.message || '{{ __('activitylog-browse::messages.strip_error_unknown') }}';

                    if (this.stripAutoRetry) {
                        this.stripRetryCount++;
                        await new Promise(r => setTimeout(r, Math.min(2000 * this.stripRetryCount, 10000)));
                        continue;
                    }

                    this.stripping = false;
                    break;
                }
            }
        },

        get stripPercent() {
            if (this.stripTotal === 0) return 0;
            return Math.min(100, Math.round((this.stripProcessed / this.stripTotal) * 100));
        }
    }" x-init="$watch('days', () => fetchPreview())">

                <h2 class="text-xl font-semibold text-gray-900 mb-6">{{ __('activitylog-browse::messages.cleanup_title') }}</h2>

                <section class="bg-white border border-1 h-full p-2 rounded">

                    {{-- Days Input --}}
                    <div class="mb-6">
                        <label for="days" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('activitylog-browse::messages.days_older_than') }}
                        </label>
                        <input type="number" id="days" x-model="days" min="1" step="1"
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

                    {{-- Action Buttons --}}
                    <div class="flex gap-3">
                        <button type="button" @click="showConfirm('delete')"
                                :disabled="!days || days < 1 || previewCount === null || previewCount === 0 || stripping"
                                class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('activitylog-browse::messages.delete_logs') }}
                        </button>

                        <button type="button" @click="showConfirm('strip')"
                                :disabled="!days || days < 1 || previewCount === null || previewCount === 0 || stripping"
                                class="inline-flex items-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-600 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            {{ __('activitylog-browse::messages.strip_properties') }}
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-gray-500">{{ __('activitylog-browse::messages.strip_properties_description') }}</p>

                    {{-- Hidden Forms --}}
                    <form x-ref="deleteForm" method="POST" action="{{ route('activitylog-browse.cleanup-delete') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="days" :value="days">
                        <template x-for="m in selectedModels" :key="m">
                            <input type="hidden" name="models[]" :value="m">
                        </template>
                    </form>

                    {{-- Strip Progress --}}
                    <div x-show="stripping || stripDone || stripError" x-transition class="mt-4 rounded-lg border p-4"
                         :class="stripDone ? 'border-green-200 bg-green-50' : (stripError && !stripping) ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium" :class="stripDone ? 'text-green-800' : (stripError && !stripping) ? 'text-red-800' : 'text-amber-800'"
                                  x-text="stripDone
                                      ? '{{ __('activitylog-browse::messages.strip_done', ['count' => '__COUNT__']) }}'.replace('__COUNT__', stripProcessed)
                                      : (stripError && !stripping) ? '{{ __('activitylog-browse::messages.strip_progress') }} ({{ __('activitylog-browse::messages.strip_stopped') }})'
                                      : '{{ __('activitylog-browse::messages.strip_progress') }}'">
                            </span>
                            <span class="text-xs" :class="stripDone ? 'text-green-600' : (stripError && !stripping) ? 'text-red-600' : 'text-amber-600'"
                                  x-text="'{{ __('activitylog-browse::messages.strip_progress_count', ['processed' => '__PROCESSED__', 'total' => '__TOTAL__']) }}'.replace('__PROCESSED__', stripProcessed).replace('__TOTAL__', stripTotal)">
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="h-3 rounded-full transition-all duration-300 ease-out"
                                 :class="stripDone ? 'bg-green-500' : (stripError && !stripping) ? 'bg-red-400' : 'bg-amber-500'"
                                 :style="'width: ' + stripPercent + '%'">
                            </div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 text-end" x-text="stripPercent + '%'"></div>

                        {{-- Auto-Retry Toggle --}}
                        <div x-show="!stripDone" class="mt-3 flex items-center gap-2">
                            <button type="button" @click="stripAutoRetry = !stripAutoRetry; if (stripAutoRetry && stripError && !stripping) startStrip();"
                                    class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-1"
                                    :class="stripAutoRetry ? 'bg-amber-500' : 'bg-gray-300'"
                                    role="switch" :aria-checked="stripAutoRetry">
                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                      :class="stripAutoRetry ? 'ltr:translate-x-4 rtl:-translate-x-4' : 'translate-x-0'"></span>
                            </button>
                            <span class="text-xs text-gray-600">{{ __('activitylog-browse::messages.strip_auto_retry') }}</span>
                        </div>

                        {{-- Error Message & Continue Button --}}
                        <div x-show="stripError" x-transition class="mt-3 flex items-center justify-between gap-3 rounded-md border border-red-200 bg-red-50 px-3 py-2">
                            <div class="flex items-center gap-2 text-sm text-red-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span x-text="stripError"></span>
                                <span x-show="stripping && stripAutoRetry" class="text-xs text-amber-600" x-text="'({{ __('activitylog-browse::messages.strip_retrying') }} #' + stripRetryCount + ')'"></span>
                            </div>
                            <button x-show="!stripping" type="button" @click="startStrip()"
                                    class="inline-flex items-center gap-1 flex-shrink-0 rounded-md bg-amber-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-amber-600 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ __('activitylog-browse::messages.strip_continue') }}
                            </button>
                            <span x-show="stripping && stripAutoRetry" class="flex-shrink-0">
                                <svg class="animate-spin h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                        </div>
                    </div>

                    {{-- Confirmation Modal --}}
                    <div x-show="confirmModal" x-transition
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                         @keydown.escape.window="confirmModal = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6"
                             @click.away="confirmModal = false">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
                                     :class="confirmAction === 'delete' ? 'bg-red-100' : 'bg-amber-100'">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                         :class="confirmAction === 'delete' ? 'text-red-600' : 'text-amber-600'"
                                         fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900"
                                    x-text="confirmAction === 'delete' ? '{{ __('activitylog-browse::messages.delete_logs') }}' : '{{ __('activitylog-browse::messages.strip_properties') }}'"></h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-6" x-text="confirmMessage"></p>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="confirmModal = false" :disabled="submitting"
                                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    {{ __('activitylog-browse::messages.cancel') }}
                                </button>
                                <button type="button" @click="submitAction()" :disabled="submitting"
                                        class="rounded-md px-4 py-2 text-sm font-medium text-white shadow-sm disabled:opacity-50"
                                        :class="confirmAction === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-500 hover:bg-amber-600'">
                                <span x-show="!submitting"
                                      x-text="confirmAction === 'delete' ? '{{ __('activitylog-browse::messages.delete_logs') }}' : '{{ __('activitylog-browse::messages.strip_properties') }}'"></span>
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
                </section>
            </div>
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
    </section>

@endsection
