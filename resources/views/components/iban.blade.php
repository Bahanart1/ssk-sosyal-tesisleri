{{-- Kopyalanabilir IBAN. Panoya kopyalar, kısa süre "Kopyalandı" gösterir. --}}
@props(['value'])

<div x-data="{
        kopyalandi: false,
        kopyala() {
            const metin = @js(preg_replace('/\s+/', '', $value));
            navigator.clipboard?.writeText(metin).then(() => {
                this.kopyalandi = true;
                setTimeout(() => this.kopyalandi = false, 1800);
            });
        }
     }"
     class="mt-0.5 flex items-center gap-2">
    <p class="font-mono text-xs text-ink">{{ $value }}</p>

    <button type="button" @click="kopyala()"
            class="rounded-md p-1 text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink"
            :aria-label="kopyalandi ? 'IBAN kopyalandı' : 'IBAN\'ı kopyala'"
            :title="kopyalandi ? 'Kopyalandı' : 'Kopyala'">
        <svg x-show="!kopyalandi" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
        <svg x-show="kopyalandi" x-cloak class="h-4 w-4" style="color: var(--status-good)" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
    </button>

    <span x-show="kopyalandi" x-cloak class="text-[11px] font-medium" style="color: var(--status-good)">Kopyalandı</span>
</div>
