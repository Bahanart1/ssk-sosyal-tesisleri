@props([
    'rows' => [],        // [['label' => '...', 'value' => 12, 'display' => '12', 'meta' => '%40'], ...]
    'ramp' => null,      // sıralı veri için rampa; verilmezse tek hue
    'max' => null,
])

@php
    $rows = array_values($rows);
    $values = array_map(fn ($r) => (float) $r['value'], $rows);
    $peak = $max ?? ($values ? max($values) : 0);
    $peak = $peak > 0 ? $peak : 1;

    // Sıralı rampa doğrulanmıştır (ordinal: monoton L, adım aralığı, açık uç kontrastı).
    $ramp = $ramp ?: ['var(--chart-series)'];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-3.5']) }}>
    @forelse ($rows as $i => $row)
        @php
            $color = $ramp[min($i, count($ramp) - 1)];
            $pct = round(((float) $row['value'] / $peak) * 100, 2);
        @endphp

        <div x-data="{ hover: false }" class="relative"
             @mouseenter="hover = true" @mouseleave="hover = false">
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <span class="flex min-w-0 items-center gap-2 text-xs font-medium text-ink">
                    <span class="h-2.5 w-2.5 flex-shrink-0 rounded-sm" style="background: {{ $color }}"></span>
                    <span class="truncate">{{ $row['label'] }}</span>
                </span>
                {{-- Doğrudan etiket: değer ucun yanında --}}
                <span class="shrink-0 text-xs font-semibold tabular-nums text-ink">{{ $row['display'] ?? $row['value'] }}</span>
            </div>

            <div class="h-3.5 w-full overflow-hidden rounded-sm bg-surface-sunken">
                <div class="h-full transition-all duration-500"
                     style="width: {{ max($pct, $row['value'] > 0 ? 1.5 : 0) }}%; background: {{ $color }}; border-radius: 0 4px 4px 0;"></div>
            </div>

            @if (! empty($row['meta']))
                <div x-show="hover" x-cloak x-transition.opacity.duration.150ms
                     class="pointer-events-none absolute right-0 -top-1 z-10 rounded-lg bg-chrome px-2.5 py-1.5 shadow-lift">
                    <span class="block text-[10px] text-chrome-muted">{{ $row['label'] }}</span>
                    <span class="block text-xs font-semibold text-white">{{ $row['meta'] }}</span>
                </div>
            @endif
        </div>
    @empty
        <div class="empty-state !py-8">
            <p class="text-sm text-ink-subtle">Gösterilecek veri yok.</p>
        </div>
    @endforelse
</div>
