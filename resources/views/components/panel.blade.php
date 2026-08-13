@props([
    'title' => null,
    'subtitle' => null,
    'action' => null,
])

{{--
    Grafik kartı. "table" slotu verilirse başlıkta bir görünüm değiştirici çıkar;
    böylece her grafiğin sayısal karşılığı da okunabilir olur.
--}}
<div x-data="{ view: 'chart' }" {{ $attributes->merge(['class' => 'surface overflow-hidden']) }}>
    @if ($title || $action || isset($table))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="font-display text-base font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs leading-relaxed text-ink-muted">{{ $subtitle }}</p>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                {{ $action }}

                @isset($table)
                    <div class="flex rounded-lg bg-surface-sunken p-0.5" role="group" aria-label="Görünüm">
                        <button type="button" @click="view = 'chart'"
                                class="rounded-md px-2 py-1 text-[11px] font-semibold transition-colors"
                                :class="view === 'chart' ? 'bg-surface text-ink shadow-sm' : 'text-ink-muted hover:text-ink'"
                                :aria-pressed="view === 'chart'">Grafik</button>
                        <button type="button" @click="view = 'table'"
                                class="rounded-md px-2 py-1 text-[11px] font-semibold transition-colors"
                                :class="view === 'table' ? 'bg-surface text-ink shadow-sm' : 'text-ink-muted hover:text-ink'"
                                :aria-pressed="view === 'table'">Tablo</button>
                    </div>
                @endisset
            </div>
        </div>
    @endif

    <div class="p-5" @isset($table) x-show="view === 'chart'" @endisset>
        {{ $slot }}
    </div>

    @isset($table)
        <div x-show="view === 'table'" x-cloak class="overflow-x-auto">
            {{ $table }}
        </div>
    @endisset
</div>
