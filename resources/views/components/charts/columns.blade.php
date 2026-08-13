@props([
    'columns' => [],     // [['label' => '14', 'value' => 3, 'display' => '3 başvuru', 'meta' => '23-29 Ağu'], ...]
    'height' => 132,
    // Tek seri, tek renk: sütun yüksekliği değeri zaten taşıdığı için
    // rengi ayrıca değere göre koyulaştırmak aynı bilgiyi iki kez kodlar.
    'color' => 'var(--chart-series)',
    'emptyColor' => 'var(--chart-empty)',
])

@php
    $columns = array_values($columns);
    $values = array_map(fn ($c) => (float) $c['value'], $columns);
    $peak = $values ? max($values) : 0;
    $peak = $peak > 0 ? $peak : 1;
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }}>
    @if (empty($columns))
        <div class="empty-state !py-8">
            <p class="text-sm text-ink-subtle">Yaklaşan açık devre yok.</p>
        </div>
    @else
        <div class="flex items-end gap-1.5" style="height: {{ $height }}px">
            @foreach ($columns as $i => $column)
                @php
                    $value = (float) $column['value'];
                    $pct = ($value / $peak) * 100;
                @endphp

                <{{ ($column['href'] ?? false) ? 'a' : 'div' }}
                     @if ($column['href'] ?? false) href="{{ $column['href'] }}" @endif
                     x-data="{ hover: false }" @mouseenter="hover = true" @mouseleave="hover = false"
                     class="group relative flex h-full flex-1 flex-col justify-end">
                    {{-- Değer, sütun kapağının üstünde; boş devreler etiketlenmez --}}
                    <span class="mb-1 h-3 text-center text-[10px] font-semibold tabular-nums leading-none text-ink">
                        {{ $value > 0 ? $column['value'] : '' }}
                    </span>

                    {{-- Sütun: üst uç 4px yuvarlak, tabanda kare --}}
                    <div class="w-full transition-all duration-500"
                         style="height: {{ max($pct, $value > 0 ? 4 : 2) }}%;
                                background: {{ $value > 0 ? $color : $emptyColor }};
                                border-radius: 4px 4px 0 0;
                                max-width: 24px; margin-inline: auto;"></div>

                    <div x-show="hover" x-cloak x-transition.opacity.duration.150ms
                         class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-lg bg-chrome px-2.5 py-1.5 shadow-lift">
                        <span class="block text-[10px] text-chrome-muted">{{ $column['meta'] ?? '' }}</span>
                        <span class="block text-xs font-semibold text-white">{{ $column['display'] ?? $column['value'] }}</span>
                    </div>
                </{{ ($column['href'] ?? false) ? 'a' : 'div' }}>
            @endforeach
        </div>

        {{-- Taban çizgisi --}}
        <div class="h-px w-full bg-line"></div>

        <div class="mt-1.5 flex gap-1.5">
            @foreach ($columns as $column)
                <span class="flex-1 text-center text-[10px] tabular-nums text-ink-muted">{{ $column['label'] }}</span>
            @endforeach
        </div>
    @endif
</div>
