@php
    $sectionKey = $sectionKey ?? '';
    $dataKey = $dataKey ?? '';
    $title = $title ?? '';
    $colLabel = $colLabel ?? '';
    $itemKey = $itemKey ?? '';
    $barColor = $barColor ?? 'bg-blue-500';
    $fullWidth = $fullWidth ?? false;
    $badge = $badge ?? false;
    $mono = $mono ?? false;
    $translate = $translate ?? false;
    $periodLabel = $periodLabel ?? false;
    $statsUrl = route('activitylog-browse.stats');
@endphp

<div x-data="{
    sections: { '{{ $sectionKey }}': { data: null, loading: true } },
    init() {
        fetch('{{ $statsUrl }}?section={{ $sectionKey }}')
            .then(r => r.json())
            .then(d => { this.sections['{{ $sectionKey }}'].data = d; this.sections['{{ $sectionKey }}'].loading = false; })
            .catch(() => { this.sections['{{ $sectionKey }}'].loading = false; });
    },
    s(name) { return this.sections[name]?.data; },
    sl(name) { return this.sections[name]?.loading; },
    maxCount(items) {
        if (!items || !items.length) return 1;
        return Math.max(...items.map(i => i.count));
    },
    pct(count, max) { return max > 0 ? Math.round((count / max) * 100) : 0; },
    hasFilter: false,
}">
    @include('activitylog-browse::partials.stats-table', [
        'sectionKey' => $sectionKey,
        'dataKey' => $dataKey,
        'title' => $title,
        'colLabel' => $colLabel,
        'itemKey' => $itemKey,
        'barColor' => $barColor,
        'fullWidth' => $fullWidth,
        'badge' => $badge,
        'mono' => $mono,
        'translate' => $translate,
        'periodLabel' => $periodLabel,
    ])
</div>
