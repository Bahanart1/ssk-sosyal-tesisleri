@props([
    'points' => [],      // [['label' => 'Oca', 'value' => 1200, 'display' => '₺1.200'], ...]
    'height' => 200,
    'label' => 'Tutar',
])

@php
    $points = array_values($points);
    $count = count($points);
    $values = array_map(fn ($p) => (float) $p['value'], $points);
    $max = $values ? max($values) : 0.0;
    $scaleMax = $max > 0 ? $max : 1.0;

    // Çizim alanı — SVG oranlı ölçeklenir, çizgi kalınlıkları non-scaling-stroke ile sabit kalır.
    $w = 720;
    $h = $height;
    $padL = 8;
    $padR = 8;
    $padT = 12;
    $padB = 26;
    $innerW = $w - $padL - $padR;
    $innerH = $h - $padT - $padB;
    $baseline = $padT + $innerH;

    $xAt = fn (int $i) => $count <= 1 ? $padL + $innerW / 2 : $padL + ($i / ($count - 1)) * $innerW;
    $yAt = fn (float $v) => $padT + $innerH - ($v / $scaleMax) * $innerH;

    $linePath = '';
    foreach ($points as $i => $p) {
        $linePath .= ($i === 0 ? 'M' : 'L') . round($xAt($i), 2) . ' ' . round($yAt((float) $p['value']), 2) . ' ';
    }
    $areaPath = $linePath
        ? $linePath . 'L' . round($xAt($count - 1), 2) . ' ' . $baseline . ' L' . round($xAt(0), 2) . ' ' . $baseline . ' Z'
        : '';

    // Alpine'a geçen diziler (ipucu konumu ve içeriği)
    $chart = [
        'active' => null,
        'xs' => array_map(fn ($i) => round($xAt($i), 2), range(0, max(0, $count - 1))),
        'pcts' => array_map(fn ($i) => round(($xAt($i) / $w) * 100, 3), range(0, max(0, $count - 1))),
        'labels' => array_column($points, 'label'),
        'displays' => array_column($points, 'display'),
    ];

    // Yalnız ilk, orta ve son etiket — kalabalık yapmaz
    $tickIndexes = $count <= 3
        ? range(0, max(0, $count - 1))
        : [0, intdiv($count - 1, 2), $count - 1];
@endphp

@if ($count === 0)
    <div {{ $attributes->merge(['class' => 'empty-state !py-10']) }}>
        <p class="text-sm text-ink-subtle">Gösterilecek veri yok.</p>
    </div>
@else
    <div x-data="{{ Illuminate\Support\Js::from($chart) }}" {{ $attributes->merge(['class' => 'relative']) }}>
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" style="height: {{ $height }}px" role="img"
             aria-label="{{ $label }} zaman serisi grafiği">
            {{-- Izgara: bir adım açık, hairline, kesintisiz --}}
            @foreach ([0, 0.5, 1] as $frac)
                @php $gy = round($padT + $innerH * $frac, 2); @endphp
                <line x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $gy }}" y2="{{ $gy }}"
                      stroke="{{ $frac === 1 ? 'var(--chart-axis)' : 'var(--chart-grid)' }}" stroke-width="1"
                      vector-effect="non-scaling-stroke" />
            @endforeach

            {{-- Alan dolgusu: seri renginin ~%10'u --}}
            <path d="{{ $areaPath }}" fill="var(--chart-series)" fill-opacity="0.12" />

            {{-- Çizgi: 2px, yuvarlak birleşim --}}
            <path d="{{ $linePath }}" fill="none" stroke="var(--chart-series)" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

            {{-- İmleç --}}
            <line x-show="active !== null" x-cloak
                  :x1="xs[active]" :x2="xs[active]" y1="{{ $padT }}" y2="{{ $baseline }}"
                  stroke="var(--chart-axis)" stroke-width="1" vector-effect="non-scaling-stroke" />

            {{-- Son nokta işaretçisi: 8px, yüzey renginde 2px halka --}}
            <circle cx="{{ round($xAt($count - 1), 2) }}" cy="{{ round($yAt((float) end($points)['value']), 2) }}"
                    r="4" fill="var(--chart-series)" stroke="var(--chart-ring)" stroke-width="2" vector-effect="non-scaling-stroke" />

            {{-- Eksen etiketleri --}}
            @foreach ($tickIndexes as $i)
                <text x="{{ round($xAt($i), 2) }}" y="{{ $h - 8 }}" fill="var(--chart-label)" font-size="11"
                      text-anchor="{{ $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') }}">{{ $points[$i]['label'] }}</text>
            @endforeach

            {{-- Dokunma hedefleri: mark'tan geniş --}}
            @foreach ($points as $i => $p)
                <rect x="{{ round($xAt($i) - $innerW / max(1, $count * 2), 2) }}" y="{{ $padT }}"
                      width="{{ round($innerW / max(1, $count), 2) }}" height="{{ $innerH }}"
                      fill="transparent" @mouseenter="active = {{ $i }}" @mouseleave="active = null" />
            @endforeach
        </svg>

        {{-- Değer ipucu --}}
        <div x-show="active !== null" x-cloak x-transition.opacity.duration.150ms
             class="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 rounded-lg bg-chrome px-2.5 py-1.5 text-center shadow-lift"
             :style="'left: ' + pcts[active] + '%'">
            <span class="block text-[10px] text-chrome-muted" x-text="labels[active]"></span>
            <span class="block text-xs font-semibold text-white" x-text="displays[active]"></span>
        </div>
    </div>
@endif
