@props([
    'rows' => [],   // [['label' => 'İnceleniyor', 'value' => 3, 'share' => 0.42, 'tone' => 'amber', 'href' => null], ...]
])

@php
    // Durum renkleri ayrılmış anlamdadır ve daima etiketle birlikte kullanılır;
    // seri renkleriyle karıştırılmaz. Değerler moda göre app.css'te çevrilir.
    $tones = [
        'amber' => 'var(--status-warn)',
        'teal' => 'var(--chart-series)',
        'green' => 'var(--status-good)',
        'red' => 'var(--status-danger)',
        'gray' => 'var(--status-neutral)',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @foreach ($rows as $row)
        @php
            $fill = $tones[$row['tone']] ?? $tones['gray'];
            $pct = round(((float) ($row['share'] ?? 0)) * 100, 1);
            $isLink = (bool) ($row['href'] ?? false);
        @endphp

        <{{ $isLink ? 'a' : 'div' }} @if ($isLink) href="{{ $row['href'] }}" @endif
            class="block {{ $isLink ? 'group' : '' }}">
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <span class="flex items-center gap-2 text-xs font-medium text-ink {{ $isLink ? 'group-hover:text-accent-600 dark:group-hover:text-accent-400' : '' }}">
                    <span class="h-2 w-2 rounded-full" style="background: {{ $fill }}"></span>
                    {{ $row['label'] }}
                </span>
                <span class="shrink-0 text-xs tabular-nums text-ink-muted">
                    <span class="font-semibold text-ink">{{ $row['value'] }}</span>
                    @if ($pct > 0) · %{{ rtrim(rtrim(number_format($pct, 1, ',', '.'), '0'), ',') }} @endif
                </span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken">
                <div class="h-full rounded-full transition-all duration-500"
                     style="width: {{ max($pct, $row['value'] > 0 ? 2 : 0) }}%; background: {{ $fill }}"></div>
            </div>
        </{{ $isLink ? 'a' : 'div' }}>
    @endforeach
</div>
