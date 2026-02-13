<form method="GET" action="{{ route('activitylog-browse.index') }}" class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.search') }}</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}"
                   placeholder="{{ __('activitylog-browse::messages.search_placeholder') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label for="log_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.log_name') }}</label>
            <select name="log_name" id="log_name"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('activitylog-browse::messages.all') }}</option>
                @foreach($logNames as $name)
                    <option value="{{ $name }}" @selected(request('log_name') === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="event" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.event') }}</label>
            <select name="event" id="event"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('activitylog-browse::messages.all') }}</option>
                @foreach($events as $event)
                    <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="subject_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.model') }}</label>
            <select name="subject_type" id="subject_type"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('activitylog-browse::messages.all') }}</option>
                @foreach($subjectTypes as $type)
                    <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </div>

        <div id="subject_id_wrapper" style="{{ request('subject_type') ? '' : 'display:none' }}">
            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.model_id') }}</label>
            <input type="text" name="subject_id" id="subject_id" value="{{ request('subject_id') }}"
                   placeholder="{{ __('activitylog-browse::messages.model_id_placeholder') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div id="changed_attribute_wrapper" style="{{ request('subject_type') ? '' : 'display:none' }}">
            <label for="changed_attribute" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.changed_attribute') }}</label>
            <select name="changed_attribute" id="changed_attribute"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('activitylog-browse::messages.all') }}</option>
            </select>
        </div>

        <div>
            <label for="causer_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.causer_type') }}</label>
            <select name="causer_type" id="causer_type"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('activitylog-browse::messages.all') }}</option>
                @foreach($causerTypes as $type)
                    <option value="{{ $type }}" @selected(request('causer_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
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
    var savedAttribute = '{{ request("changed_attribute") }}';

    function fetchAttributes(subjectType) {
        var wrapper = document.getElementById('changed_attribute_wrapper');
        var select = document.getElementById('changed_attribute');
        var allLabel = '{{ __("activitylog-browse::messages.all") }}';

        if (!subjectType) {
            wrapper.style.display = 'none';
            select.innerHTML = '<option value="">' + allLabel + '</option>';
            return;
        }

        wrapper.style.display = '';

        fetch(attributesUrl + '?subject_type=' + encodeURIComponent(subjectType))
            .then(function(r) { return r.json(); })
            .then(function(attrs) {
                var html = '<option value="">' + allLabel + '</option>';
                attrs.forEach(function(attr) {
                    var selected = (attr === savedAttribute) ? ' selected' : '';
                    html += '<option value="' + attr + '"' + selected + '>' + attr + '</option>';
                });
                select.innerHTML = html;
            });
    }

    document.getElementById('subject_type').addEventListener('change', function () {
        var wrapper = document.getElementById('subject_id_wrapper');
        var input = document.getElementById('subject_id');
        if (this.value) {
            wrapper.style.display = '';
        } else {
            wrapper.style.display = 'none';
            input.value = '';
        }
        fetchAttributes(this.value);
    });

    // Load attributes on page load if model is pre-selected
    if (document.getElementById('subject_type').value) {
        fetchAttributes(document.getElementById('subject_type').value);
    }

    document.getElementById('causer_type').addEventListener('change', function () {
        var wrapper = document.getElementById('causer_id_wrapper');
        var input = document.getElementById('causer_id');
        if (this.value) {
            wrapper.style.display = '';
        } else {
            wrapper.style.display = 'none';
            input.value = '';
        }
    });
</script>
