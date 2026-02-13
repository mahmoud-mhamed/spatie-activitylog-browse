@props(['name', 'label', 'allLabel', 'selected' => '', 'options' => []])

@php
    $selectedLabel = $allLabel;
    foreach ($options as $opt) {
        if ((string) $opt['value'] === (string) $selected) {
            $selectedLabel = $opt['label'];
            break;
        }
    }
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    <div x-data="{
            open: false,
            search: '',
            value: @js($selected),
            displayLabel: @js($selectedLabel),
            allLabel: @js($allLabel),
            options: @js($options),
            get filtered() {
                if (!this.search) return this.options;
                let s = this.search.toLowerCase();
                return this.options.filter(o => o.label.toLowerCase().includes(s));
            },
            pick(val, label) {
                this.value = val;
                this.displayLabel = val ? label : this.allLabel;
                this.search = '';
                this.open = false;
                this.$refs.hidden.setAttribute('value', val);
                this.$refs.hidden.dispatchEvent(new Event('change'));
            }
         }"
         @click.outside="open = false; search = ''"
         @keydown.escape.window="open = false; search = ''"
         class="relative">

        <input type="hidden" name="{{ $name }}" x-ref="hidden" :value="value">

        <button type="button"
                @click="open = !open; $nextTick(() => { if(open) $refs.searchInput.focus() })"
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
            <ul class="max-h-48 overflow-y-auto py-1">
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
                <li x-show="filtered.length === 0 && search" class="px-3 py-1.5 text-sm text-gray-400 italic">
                    {{ __('activitylog-browse::messages.no_data') }}
                </li>
            </ul>
        </div>
    </div>
</div>
