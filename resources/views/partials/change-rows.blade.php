{{--
    Old/new table for ValuePresenter::changeRows(): translated labels, enum labels next to raw values,
    and only the changed part of long texts highlighted. Used by the show page and the timeline.
    Expects: $rows, $hasOld, $hasNew
--}}
<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.attribute') }}</th>
            @if($hasOld)
                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.old') }}</th>
            @endif
            @if($hasNew)
                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.new') }}</th>
            @endif
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        @foreach($rows as $row)
            <tr>
                <td class="px-4 py-2 text-sm font-medium text-gray-700 align-top whitespace-nowrap" title="{{ $row['key'] }}">{{ $row['label'] }}</td>
                @foreach(array_filter(['old' => $hasOld, 'new' => $hasNew]) as $side => $_)
                    <td class="px-4 py-2 text-sm align-top break-all {{ $side === 'old' ? 'text-red-700 bg-red-50' : 'text-green-700 bg-green-50' }}" dir="auto">
                        @if(! empty($row['same_content']))
                            <span class="text-xs italic text-gray-500" title="{{ $row[$side] }}">{{ __('activitylog-browse::messages.same_content_note') }}</span>
                        @elseif(isset($row["{$side}_parts"]))
                            @foreach($row["{$side}_parts"] as [$text, $changed])
                                <span class="{{ $changed ? ($side === 'old' ? 'bg-red-200' : 'bg-green-200') . ' rounded px-0.5' : 'text-gray-500' }}">{{ $text }}</span>
                            @endforeach
                        @elseif($row[$side] === null)
                            <span class="italic text-gray-400">{{ __('activitylog-browse::messages.null') }}</span>
                        @elseif(isset($row["{$side}_display"]))
                            {{ $row["{$side}_display"] }} <span class="text-xs text-gray-400 font-mono">{{ $row[$side] }}</span>
                        @else
                            {{ $row[$side] }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
