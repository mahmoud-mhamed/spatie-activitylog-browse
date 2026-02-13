<form method="GET" action="{{ route('activitylog-browse.index') }}" class="bg-white rounded-lg shadow p-4 mb-6">
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
            <a href="{{ route('activitylog-browse.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                {{ __('activitylog-browse::messages.reset') }}
            </a>
        </div>
    </div>
</form>

<script>
    var attributesUrl = '{{ route("activitylog-browse.attributes") }}';
    var causersUrl = '{{ route("activitylog-browse.causers") }}';
    var savedAttribute = '{{ request("changed_attribute") }}';
    var allLabel = '{{ __("activitylog-browse::messages.all") }}';

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
            });

            // Load attributes on page load if model is pre-selected
            if (subjectHidden.value) {
                fetchAttributes(subjectHidden.value);
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
