<x-layouts.customer title="Ödeme">

    <div x-data="{ method: 'credit_card' }" class="mx-auto max-w-lg">
        <a href="{{ route('customer.reservations.show', $reservation) }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Rezervasyon detayına dön
        </a>
        <p class="section-label mt-4">Ödeme</p>
        <h1 class="page-title mt-1 mb-6">Ödemeyi tamamla</h1>

        <div class="surface mb-6 overflow-hidden">
            <div class="bg-navy-900 px-6 py-4">
                <p class="text-sm text-navy-200">{{ $reservation->facility->name }}</p>
                <p class="mt-1 font-display text-lg font-semibold text-white">{{ $reservation->nights() }} gece</p>
            </div>
            <div class="flex items-center justify-between px-6 py-4">
                <span class="font-semibold text-navy-900">Ödenecek tutar</span>
                <span class="font-display text-2xl font-semibold text-teal-700">₺{{ number_format($reservation->total_price, 0, ',', '.') }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('customer.payment.process', $reservation) }}">
            @csrf
            <p class="field-label mb-3">Ödeme yöntemi</p>
            <div class="space-y-3">
                <label class="choice-tile cursor-pointer" :class="method === 'credit_card' ? 'choice-tile-active' : 'choice-tile-idle'">
                    <div class="flex w-full items-center gap-3">
                        <input type="radio" name="method" value="credit_card" x-model="method" class="text-teal-600 focus:ring-teal-500">
                        <svg class="h-5 w-5 text-navy-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                        <span class="text-sm font-semibold text-navy-900">Kredi / Banka Kartı</span>
                    </div>
                </label>
                <label class="choice-tile cursor-pointer" :class="method === 'bank_transfer' ? 'choice-tile-active' : 'choice-tile-idle'">
                    <div class="flex w-full items-center gap-3">
                        <input type="radio" name="method" value="bank_transfer" x-model="method" class="text-teal-600 focus:ring-teal-500">
                        <svg class="h-5 w-5 text-navy-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 3h15M5.25 8.25v10.5m4.5-10.5v10.5m4.5-10.5v10.5m4.5-10.5v10.5M2.25 8.25 12 3l9.75 5.25" /></svg>
                        <span class="text-sm font-semibold text-navy-900">Havale / EFT</span>
                    </div>
                </label>
            </div>

            <div x-show="method === 'credit_card'" x-transition class="mt-5 space-y-4 rounded-xl2 border border-stone-200/80 bg-white/70 p-4">
                <div><label class="field-label">Kart üzerindeki isim</label><input type="text" class="field-input" placeholder="Ad Soyad" autocomplete="cc-name"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="field-label">Kart numarası</label><input type="text" class="field-input" placeholder="•••• •••• •••• ••••" autocomplete="cc-number"></div>
                    <div><label class="field-label">SKT / CVV</label><input type="text" class="field-input" placeholder="AA/YY · CVV" autocomplete="cc-exp"></div>
                </div>
                <p class="field-hint">Demo ödeme ekranıdır; gerçek kart bilgisi işlenmez.</p>
            </div>

            <button type="submit" class="btn-accent mt-6 w-full py-3">₺{{ number_format($reservation->total_price, 0, ',', '.') }} Öde</button>
        </form>
    </div>
</x-layouts.customer>
