<form id="activitylog-browse-filters" method="GET" action="{{ route('activitylog-browse.index') }}" class="bg-white rounded-lg shadow p-4 mb-6">
    @if(request('via_activity'))
        <input type="hidden" name="via_activity" value="{{ request('via_activity') }}">
    @endif
    @if(request('via_relation'))
        <input type="hidden" name="via_relation" value="{{ request('via_relation') }}">
    @endif
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.search') }}</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}"
                   placeholder="{{ __('activitylog-browse::messages.search_placeholder') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        @include('activitylog-browse::partials.searchable-select', [
            'name' => 'log_name',
            'label' => __('activitylog-browse::messages.log_name'),
            'allLabel' => __('activitylog-browse::messages.all'),
            'selected' => request('log_name', ''),
            'fetchUrl' => route('activitylog-browse.filter-options', ['column' => 'log_name']),
        ])

        @include('activitylog-browse::partials.searchable-select', [
            'name' => 'event',
            'label' => __('activitylog-browse::messages.event'),
            'allLabel' => __('activitylog-browse::messages.all'),
            'selected' => request('event', ''),
            'fetchUrl' => route('activitylog-browse.filter-options', ['column' => 'event']),
        ])

        @include('activitylog-browse::partials.searchable-select', [
            'name' => 'subject_type',
            'label' => __('activitylog-browse::messages.model'),
            'allLabel' => __('activitylog-browse::messages.all'),
            'selected' => request('subject_type', ''),
            'fetchUrl' => route('activitylog-browse.filter-options', ['column' => 'subject_type']),
        ])

        <div id="subject_id_wrapper" style="{{ request('subject_type') ? '' : 'display:none' }}">
            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.model_id') }}</label>
            <input type="text" name="subject_id" id="subject_id" value="{{ request('subject_id') }}"
                   placeholder="{{ __('activitylog-browse::messages.model_id_placeholder') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div id="changed_attribute_wrapper" style="{{ request('subject_type') ? '' : 'display:none' }}">
            @include('activitylog-browse::partials.searchable-select', [
                'name' => 'changed_attribute',
                'label' => __('activitylog-browse::messages.changed_attribute'),
                'allLabel' => __('activitylog-browse::messages.all'),
                'selected' => request('changed_attribute', ''),
                'options' => [],
            ])
        </div>

        @include('activitylog-browse::partials.searchable-select', [
            'name' => 'causer_type',
            'label' => __('activitylog-browse::messages.causer_type'),
            'allLabel' => __('activitylog-browse::messages.all'),
            'selected' => request('causer_type', ''),
            'fetchUrl' => route('activitylog-browse.filter-options', ['column' => 'causer_type']),
        ])

        <div id="causer_select_wrapper" style="{{ request('causer_type') ? '' : 'display:none' }}">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.select_causer') }}</label>
            <div x-data="{
                    open: false,
                    search: '',
                    options: [],
                    loaded: false,
                    loading: false,
                    value: @js(request('causer_id', '')),
                    displayLabel: @js(request('causer_id') ? '#' . request('causer_id') : __('activitylog-browse::messages.all')),
                    allLabel: @js(__('activitylog-browse::messages.all')),
                    get filtered() {
                        if (!this.search) return this.options;
                        let s = this.search.toLowerCase();
                        return this.options.filter(o => o.label.toLowerCase().includes(s));
                    },
                    toggle() {
                        this.open = !this.open;
                        if (this.open) {
                            if (!this.loaded) this.fetchCausers();
                            this.$nextTick(() => this.$refs.searchInput.focus());
                        }
                    },
                    fetchCausers() {
                        var causerType = document.querySelector('input[name=causer_type]')?.value;
                        if (!causerType) return;
                        this.loading = true;
                        fetch(causersUrl + '?causer_type=' + encodeURIComponent(causerType))
                            .then(r => r.json())
                            .then(data => {
                                this.options = data;
                                this.loaded = true;
                                this.loading = false;
                                if (this.value) {
                                    var match = data.find(o => o.value === this.value);
                                    if (match) this.displayLabel = match.label;
                                }
                            })
                            .catch(() => { this.loading = false; });
                    },
                    pick(val, label) {
                        this.value = val;
                        this.displayLabel = val ? label : this.allLabel;
                        this.search = '';
                        this.open = false;
                        var causerIdInput = document.getElementById('causer_id');
                        if (causerIdInput) causerIdInput.value = val;
                    },
                    reset() {
                        this.options = [];
                        this.loaded = false;
                        this.loading = false;
                        this.value = '';
                        this.displayLabel = this.allLabel;
                    }
                 }"
                 @click.outside="open = false; search = ''"
                 @keydown.escape.window="open = false; search = ''"
                 class="relative"
                 id="causer_select_alpine">

                <button type="button" @click="toggle()"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500 bg-white text-start flex items-center justify-between">
                    <span x-text="displayLabel" class="truncate" :class="value ? 'text-gray-900' : 'text-gray-500'"></span>
                    <svg class="h-4 w-4 text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="open" x-transition.opacity.duration.150ms
                     class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg">
                    <div class="p-2">
                        <input x-ref="searchInput" type="text" x-model="search"
                               placeholder="{{ __('activitylog-browse::messages.search') }}..."
                               @keydown.enter.prevent="if(filtered.length === 1) pick(filtered[0].value, filtered[0].label)"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm px-2 py-1.5 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div x-show="loading" class="px-3 py-2 text-sm text-gray-400 italic">
                        {{ __('activitylog-browse::messages.loading') }}...
                    </div>
                    <ul x-show="!loading" class="max-h-48 overflow-y-auto py-1">
                        <li @click="pick('', allLabel)"
                            class="px-3 py-1.5 text-sm cursor-pointer hover:bg-blue-50"
                            :class="!value ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-500'">
                            <span x-text="allLabel"></span>
                        </li>
                        <template x-for="opt in filtered" :key="opt.value">
                            <li @click="pick(opt.value, opt.label)"
                                class="px-3 py-1.5 text-sm cursor-pointer hover:bg-blue-50"
                                :class="opt.value === value ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'">
                                <span x-text="opt.label"></span>
                            </li>
                        </template>
                        <li x-show="loaded && filtered.length === 0 && search" class="px-3 py-1.5 text-sm text-gray-400 italic">
                            {{ __('activitylog-browse::messages.no_data') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="causer_id_wrapper" style="{{ request('causer_type') ? '' : 'display:none' }}">
            <label for="causer_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.causer_id') }}</label>
            <input type="text" name="causer_id" id="causer_id" value="{{ request('causer_id') }}"
                   placeholder="{{ __('activitylog-browse::messages.causer_id_placeholder') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.from') }}</label>
            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.to') }}</label>
            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                {{ __('activitylog-browse::messages.filter') }}
            </button>
            <a href="{{ route('activitylog-browse.index', array_filter([
                    'via_activity' => request('via_activity'),
                    'via_relation' => request('via_relation'),
                    'subject_type' => request('via_activity') ? request('subject_type') : null,
                    'subject_id' => request('via_activity') ? request('subject_id') : null,
                ])) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                {{ __('activitylog-browse::messages.reset') }}
            </a>
        </div>
    </div>
</form>

<div id="model_info_card" style="display:none" class="bg-white rounded-lg shadow p-4 mb-6"
     x-data="{
        loading: false,
        info: null,
        search: '',
        formatSize(bytes) {
            if (!bytes) return '-';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0, size = bytes;
            for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
            return size.toFixed(1) + ' ' + units[i];
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
        <template x-if="info && info.attributes.length > 8">
            <input type="text" x-model="search"
                   placeholder="{{ __('activitylog-browse::messages.search') }}..."
                   class="rounded-md border-gray-300 shadow-sm text-sm px-3 py-1.5 border focus:border-blue-500 focus:ring-blue-500 w-48">
        </template>
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
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.total_logs') }}</div>
                    <div class="text-lg font-bold text-gray-900" x-text="info.stats.total_logs?.toLocaleString()"></div>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.unique_records') }}</div>
                    <div class="text-lg font-bold text-gray-900" x-text="info.stats.unique_subjects?.toLocaleString()"></div>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.table_name') }}</div>
                    <div class="text-lg font-bold text-gray-900 font-mono" x-text="info.stats.table_name ?? '-'"></div>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.stats_table_size') }}</div>
                    <div class="text-lg font-bold text-gray-900" x-text="formatSize(info.stats.table_size)"></div>
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.event') }}</div>
                    <div class="flex flex-wrap gap-1 mt-1">
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
                </div>
            </div>

            {{-- Attributes grid --}}
            <div class="mb-2">
                <div class="text-xs font-medium text-gray-500 uppercase mb-2">
                    {{ __('activitylog-browse::messages.model_attributes') }}
                    <span class="text-gray-400 normal-case" x-text="'(' + info.attributes.length + ')'"></span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="attr in filteredAttrs" :key="attr.key">
                        <button type="button"
                                @click="
                                    var form = document.getElementById('activitylog-browse-filters');
                                    var hiddenInput = form.querySelector('input[name=changed_attribute]');
                                    if (hiddenInput) {
                                        hiddenInput.value = attr.key;
                                    }
                                    form.submit();
                                "
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-lg border cursor-pointer transition-colors hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700"
                                :class="attr.has_translation ? 'bg-white border-gray-200 text-gray-700' : 'bg-white border-gray-200 text-gray-500'"
                                :title="'{{ __('activitylog-browse::messages.click_to_filter') }}'">
                            <span class="font-medium" x-text="attr.label"></span>
                            <span class="text-gray-400" x-show="attr.has_translation" x-text="'(' + attr.key + ')'"></span>
                            <span x-show="!attr.has_translation" class="font-mono text-gray-400" x-text="attr.key === attr.label ? '' : attr.key"></span>
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

<script>
    var attributesUrl = '{{ route("activitylog-browse.attributes") }}';
    var modelInfoUrl = '{{ route("activitylog-browse.model-info") }}';
    var causersUrl = '{{ route("activitylog-browse.causers") }}';
    var savedAttribute = '{{ request("changed_attribute") }}';
    var allLabel = '{{ __("activitylog-browse::messages.all") }}';

    function fetchModelInfo(subjectType) {
        var card = document.getElementById('model_info_card');
        var d = Alpine.$data(card);

        if (!subjectType) {
            card.style.display = 'none';
            d.info = null;
            d.search = '';
            return;
        }

        card.style.display = '';
        d.loading = true;
        d.info = null;
        d.search = '';

        fetch(modelInfoUrl + '?subject_type=' + encodeURIComponent(subjectType))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                d.info = data;
                d.loading = false;
            })
            .catch(function() {
                d.loading = false;
            });
    }

    function fetchAttributes(subjectType) {
        var wrapper = document.getElementById('changed_attribute_wrapper');
        var attrEl = document.querySelector('#changed_attribute_wrapper [x-data]');

        if (!subjectType) {
            wrapper.style.display = 'none';
            if (attrEl) {
                var d = Alpine.$data(attrEl);
                d.options = [];
                d.pick('', allLabel);
            }
            return;
        }

        wrapper.style.display = '';

        fetch(attributesUrl + '?subject_type=' + encodeURIComponent(subjectType))
            .then(function(r) { return r.json(); })
            .then(function(attrs) {
                if (!attrEl) return;
                var d = Alpine.$data(attrEl);
                d.options = attrs.map(function(a) { return { value: a, label: a }; });
                if (savedAttribute) {
                    d.pick(savedAttribute, savedAttribute);
                    savedAttribute = '';
                } else {
                    d.pick('', allLabel);
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Listen for subject_type changes
        var subjectHidden = document.querySelector('input[name="subject_type"]');
        if (subjectHidden) {
            subjectHidden.addEventListener('change', function() {
                var val = this.value;
                var idWrapper = document.getElementById('subject_id_wrapper');
                var idInput = document.getElementById('subject_id');
                if (val) {
                    idWrapper.style.display = '';
                } else {
                    idWrapper.style.display = 'none';
                    idInput.value = '';
                }
                fetchAttributes(val);
                fetchModelInfo(val);
            });

            // Load attributes + model info on page load if model is pre-selected
            if (subjectHidden.value) {
                fetchAttributes(subjectHidden.value);
                fetchModelInfo(subjectHidden.value);
            }
        }

        // Listen for causer_type changes
        var causerHidden = document.querySelector('input[name="causer_type"]');
        if (causerHidden) {
            causerHidden.addEventListener('change', function() {
                var val = this.value;
                var idWrapper = document.getElementById('causer_id_wrapper');
                var idInput = document.getElementById('causer_id');
                var selectWrapper = document.getElementById('causer_select_wrapper');
                var selectEl = document.getElementById('causer_select_alpine');

                if (val) {
                    idWrapper.style.display = '';
                    selectWrapper.style.display = '';
                } else {
                    idWrapper.style.display = 'none';
                    selectWrapper.style.display = 'none';
                    idInput.value = '';
                }

                // Reset the causer select so it re-fetches for the new type
                if (selectEl) {
                    var d = Alpine.$data(selectEl);
                    d.reset();
                }
            });
        }
    });
</script>
