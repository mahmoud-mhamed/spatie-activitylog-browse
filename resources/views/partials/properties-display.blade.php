@if($properties && count($properties))
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.key') }}</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.value') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($properties as $key => $value)
                    <tr>
                        <td class="px-4 py-2 text-sm font-medium text-gray-700">{{ $key }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">
                            @if(is_array($value) || is_object($value))
                                <pre class="text-xs bg-gray-50 p-2 rounded overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            @elseif(is_null($value))
                                <span class="text-gray-400 italic">{{ __('activitylog-browse::messages.null') }}</span>
                            @elseif(is_bool($value))
                                <span class="text-gray-400 italic">{{ $value ? __('activitylog-browse::messages.true') : __('activitylog-browse::messages.false') }}</span>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500 italic">{{ __('activitylog-browse::messages.no_data') }}</p>
@endif
