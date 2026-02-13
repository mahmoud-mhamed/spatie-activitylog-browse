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
            'options' => $logNames->map(fn($n) => ['value' => $n, 'label' => $n])->all(),
        ])

        @include('activitylog-browse::partials.searchable-select', [
            'name' => 'event',
            'label' => __('activitylog-browse::messages.event'),
            'allLabel' => __('activitylog-browse::messages.all'),
            'selected' => request('event', ''),
            'options' => $events->map(fn($e) => ['value' => $e, 'label' => $e])->all(),
        ])

        @include('activitylog-browse::partials.searchable-select', [
            'name' => 'subject_type',
            'label' => __('activitylog-browse::messages.model'),
            'allLabel' => __('activitylog-browse::messages.all'),
            'selected' => request('subject_type', ''),
            'options' => $subjectTypes->map(fn($t) => ['value' => $t, 'label' => class_basename($t)])->all(),
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
            'options' => $causerTypes->map(fn($t) => ['value' => $t, 'label' => class_basename($t)])->all(),
        ])

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
                if (val) {
                    idWrapper.style.display = '';
                } else {
                    idWrapper.style.display = 'none';
                    idInput.value = '';
                }
            });
        }
    });
</script>
