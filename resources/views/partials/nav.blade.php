@php
    $currentLocale = app()->getLocale();
    $switchLocale  = $currentLocale === 'ar' ? 'en' : 'ar';
    $switchLabel   = $currentLocale === 'ar' ? 'EN' : 'AR';
    $isPasswordAuthed = session(\Mhamed\SpatieActivitylogBrowse\Http\Middleware\RequirePassword::SESSION_KEY);

    $navItems = [
        [
            'route' => 'activitylog-browse.index',
            'label' => __('activitylog-browse::messages.activity_log'),
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            'active' => request()->routeIs('activitylog-browse.index') || request()->routeIs('activitylog-browse.show'),
        ],
        [
            'route' => 'activitylog-browse.statistics',
            'label' => __('activitylog-browse::messages.statistics'),
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
            'active' => request()->routeIs('activitylog-browse.statistics'),
        ],
        [
            'route' => 'activitylog-browse.cleanup',
            'label' => __('activitylog-browse::messages.cleanup'),
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
            'active' => request()->routeIs('activitylog-browse.cleanup*'),
        ],
        [
            'route' => 'activitylog-browse.about',
            'label' => __('activitylog-browse::messages.about'),
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
            'active' => request()->routeIs('activitylog-browse.about'),
        ],
    ];
@endphp

<nav class="alb-nav sticky top-0 z-30 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="w-full px-4 sm:px-6 lg:px-8 h-14 flex items-center gap-3">
        {{-- Brand --}}
        <a href="{{ route('activitylog-browse.index') }}"
           class="flex items-center gap-2 font-bold text-blue-600 hover:text-blue-700 shrink-0">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            <span class="text-sm tracking-tight whitespace-nowrap">{{ __('activitylog-browse::messages.activity_log') }}</span>
        </a>

        {{-- Pill nav (desktop) --}}
        <div class="alb-nav-pills hidden md:flex items-center gap-1 ms-4 bg-gray-100 dark:bg-gray-900 rounded-lg p-1">
            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="px-3 py-1 rounded-md text-xs font-medium whitespace-nowrap transition-all
                          {{ $item['active']
                                ? 'bg-white dark:bg-gray-700 text-blue-600 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Right side --}}
        <div class="ms-auto flex items-center gap-1.5">
            <span class="hidden lg:inline-block text-[11px] font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 rounded-full px-2.5 py-0.5">
                {{ config('app.name') }} &middot; {{ app()->environment() }}
            </span>

            <a href="{{ route('activitylog-browse.switch-lang', $switchLocale) }}"
               title="{{ $switchLabel }}"
               class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-900 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-md transition-colors">
                {{ $switchLabel }}
            </a>

            <button type="button" onclick="window.__toggleTheme()"
                    title="{{ __('activitylog-browse::messages.toggle_theme') }}"
                    class="inline-flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-900 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-md transition-colors">
                <svg class="w-4 h-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                <svg class="w-4 h-4 hidden dark:inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2m0 18v2m11-11h-2M3 12H1m16.95-6.95l-1.414 1.414M6.464 17.536l-1.414 1.414m12.728 0l-1.414-1.414M6.464 6.464L5.05 5.05"/></svg>
            </button>

            @if($isPasswordAuthed)
                <form method="POST" action="{{ route('activitylog-browse.logout') }}" class="m-0 hidden md:block">
                    @csrf
                    <button type="submit"
                            title="{{ __('activitylog-browse::messages.logout') }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 dark:text-red-400 bg-gray-100 dark:bg-gray-900 hover:bg-red-50 dark:hover:bg-red-900 border border-gray-200 dark:border-gray-700 rounded-md transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </form>
            @endif

            {{-- Mobile menu toggle --}}
            <button type="button" id="alb-nav-toggle"
                    onclick="window.__albToggleNav()"
                    class="md:hidden inline-flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-900 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-md transition-colors"
                    aria-label="Menu">
                <svg id="alb-nav-icon-open" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
                <svg id="alb-nav-icon-close" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile dropdown --}}
    <div id="alb-nav-dropdown"
         class="md:hidden hidden bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-md">
        <div class="px-3 py-2 space-y-1">
            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors
                          {{ $item['active']
                                ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <span class="w-4 h-4 inline-flex items-center justify-center opacity-70">
                        {!! $item['icon'] !!}
                    </span>
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if($isPasswordAuthed)
                <form method="POST" action="{{ route('activitylog-browse.logout') }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900 transition-colors">
                        <span class="w-4 h-4 inline-flex items-center justify-center opacity-70">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        </span>
                        {{ __('activitylog-browse::messages.logout') }}
                    </button>
                </form>
            @endif

            <div class="px-3 pt-2 mt-2 border-t border-gray-200 dark:border-gray-700 text-[11px] text-gray-500 dark:text-gray-400">
                {{ config('app.name') }} &middot; {{ app()->environment() }}
            </div>
        </div>
    </div>
</nav>

<script>
    window.__albToggleNav = function () {
        var dd = document.getElementById('alb-nav-dropdown');
        var iOpen = document.getElementById('alb-nav-icon-open');
        var iClose = document.getElementById('alb-nav-icon-close');
        if (!dd) return;
        var isOpen = dd.classList.toggle('hidden');
        iOpen.classList.toggle('hidden', !isOpen);
        iClose.classList.toggle('hidden', isOpen);
    };

    document.addEventListener('click', function (e) {
        var dd = document.getElementById('alb-nav-dropdown');
        var btn = document.getElementById('alb-nav-toggle');
        if (!dd || dd.classList.contains('hidden')) return;
        if (dd.contains(e.target) || (btn && btn.contains(e.target))) return;
        dd.classList.add('hidden');
        document.getElementById('alb-nav-icon-open').classList.remove('hidden');
        document.getElementById('alb-nav-icon-close').classList.add('hidden');
    });
</script>
