@props([
    'label' => '',
    'value' => '',
    'delta' => null,      // ['text' => '+%12', 'positive' => true, 'period' => 'önceki 30 güne göre']
    'spark' => [],        // sayı dizisi — son nokta vurgulanır
    'href' => null,
    'hero' => false,
    'hint' => null,
])

@php
    $tag = $href ? 'a' : 'div';

    // Kıvılcım çizgisi: bağlam de-emphasis renginde, son nokta vurguda
    $spark = array_values(array_map('floatval', $spark));
    $n = count($spark);
    $sparkMax = $n ? max($spark) : 0;
    $sparkMin = $n ? min($spark) : 0;
    $range = ($sparkMax - $sparkMin) ?: 1;
    $sw = 120; $sh = 32;
    $sparkPath = '';
    foreach ($spark as $i => $v) {
        $sx = $n <= 1 ? $sw / 2 : ($i / ($n - 1)) * $sw;
        $sy = $sh - (($v - $sparkMin) / $range) * ($sh - 4) - 2;
        $sparkPath .= ($i === 0 ? 'M' : 'L') . round($sx, 2) . ' ' . round($sy, 2) . ' ';
    }
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'stat-card block' . ($href ? ' surface-hover' : '')]) }}
    >

    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-muted">{{ $label }}</p>

    <div class="mt-2 flex items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="font-sans font-semibold leading-none text-ink {{ $hero ? 'text-[2.75rem]' : 'text-3xl' }}">{{ $value }}</p>

            @if ($delta)
                <p class="mt-2 flex items-center gap-1.5 text-xs">
                    <span class="inline-flex items-center gap-0.5 font-semibold {{ $delta['positive'] ? 'text-emerald-700' : 'text-red-600' }}">
                        @if ($delta['positive'])
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                        @else
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        @endif
                        {{ $delta['text'] }}
                    </span>
                    <span class="text-ink-muted">{{ $delta['period'] }}</span>
                </p>
            @elseif ($hint)
                <p class="mt-2 text-xs text-ink-muted">{{ $hint }}</p>
            @endif
        </div>

        @if ($n > 1)
            <svg viewBox="0 0 {{ $sw }} {{ $sh }}" class="h-8 w-[120px] shrink-0" aria-hidden="true">
                <path d="{{ $sparkPath }}" fill="none" stroke="var(--chart-spark)" stroke-width="2"
                      stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                <circle cx="{{ round($n <= 1 ? $sw / 2 : $sw, 2) }}"
                        cy="{{ round($sh - ((end($spark) - $sparkMin) / $range) * ($sh - 4) - 2, 2) }}"
                        r="3" fill="var(--chart-series)" />
            </svg>
        @endif
    </div>
</{{ $tag }}>
