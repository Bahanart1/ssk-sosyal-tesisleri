<x-layouts.customer title="Bakiye Ödemesi">

    <div x-data="{ method: 'card', installment: {{ (int) (config('payment.installments')[0] ?? 1) }} }" class="mx-auto max-w-2xl">
        <a href="{{ route('customer.reservations.show', $reservation) }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Başvuruya dön
        </a>

        <div class="mt-4 mb-6">
            <p class="section-label">Bakiye ödemesi</p>
            <h1 class="page-title mt-1">{{ $reservation->code }}</h1>
            <p class="page-subtitle">
                {{ $reservation->facility->name }} · {{ $reservation->roomType->name }} ·
                {{ $reservation->start_date->translatedFormat('d F') }} – {{ $reservation->end_date->translatedFormat('d F Y') }}
            </p>
        </div>

        <div class="surface mb-6 overflow-hidden">
            <div class="divide-y divide-line">
                <div class="flex justify-between px-6 py-3.5 text-sm">
                    <span class="text-ink-muted">Toplam tutar</span>
                    <x-money :value="$reservation->total_price" class="font-medium text-ink" />
                </div>
                <div class="flex justify-between px-6 py-3.5 text-sm">
                    <span class="text-ink-muted">Ödenen (peşinat dahil)</span>
                    <x-money :value="$reservation->paidTotal()" class="font-medium text-ink" />
                </div>
                <div class="flex items-center justify-between bg-chrome px-6 py-4 text-white">
                    <span class="font-semibold">Ödenecek bakiye</span>
                    <x-money :value="$balance" class="font-display text-2xl font-semibold" />
                </div>
                @if ($reservation->balance_due_date)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-ink-muted">Son ödeme tarihi</span>
                        <span class="font-medium text-ink">{{ $reservation->balance_due_date->translatedFormat('d F Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Yöntem seçimi --}}
        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            <button type="button" @click="method = 'card'" class="choice-tile"
                    :class="method === 'card' ? 'choice-tile-active' : 'choice-tile-idle'">
                <span class="font-semibold text-ink">Kredi / Banka kartı</span>
                <span class="text-xs text-ink-muted">Sanal POS üzerinden, taksit imkanıyla.</span>
            </button>
            <button type="button" @click="method = 'transfer'" class="choice-tile"
                    :class="method === 'transfer' ? 'choice-tile-active' : 'choice-tile-idle'">
                <span class="font-semibold text-ink">Havale / EFT</span>
                <span class="text-xs text-ink-muted">Dernek hesabına yatırıp dekontu yükleyin.</span>
            </button>
        </div>

        {{-- Kart --}}
        <form x-show="method === 'card'" method="POST" action="{{ route('customer.payment.card', $reservation) }}" class="surface p-6">
            @csrf
            <h2 class="font-display text-lg font-semibold text-ink">Ödeme seçenekleri</h2>
            <p class="mt-1 text-sm text-ink-muted">Bakiye tutarı peşin veya banka kartına taksitle ödenebilir.</p>

            <div class="mt-5 space-y-2">
                @foreach ($installments as $option)
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-4 py-3 transition-all"
                           :class="installment === {{ $option['installment'] }} ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/30 ring-2 ring-accent-500' : 'border-line hover:border-accent-300'">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="installment" value="{{ $option['installment'] }}"
                                   x-model.number="installment" class="text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            <span class="text-sm font-medium text-ink">{{ $option['label'] }}</span>
                        </span>
                        <span class="text-right">
                            <x-money :value="$option['total']" class="block text-sm font-semibold text-ink" />
                            @if ($option['installment'] > 1)
                                <span class="text-[11px] text-ink-muted">aylık <x-money :value="$option['monthly']" /></span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>

            @error('installment') <p class="field-error">{{ $message }}</p> @enderror

            <button type="submit" class="btn-accent mt-6 w-full py-3">Güvenli Ödemeye Geç</button>
            <p class="field-hint text-center">Bankanın 3D Secure sayfasına yönlendirileceksiniz. Kart bilgileriniz Dernek sistemlerinde saklanmaz.</p>
        </form>

        {{-- Havale --}}
        <form x-show="method === 'transfer'" x-cloak method="POST" action="{{ route('customer.payment.transfer', $reservation) }}"
              enctype="multipart/form-data" class="surface p-6">
            @csrf
            <h2 class="font-display text-lg font-semibold text-ink">Havale / EFT bildirimi</h2>
            <p class="mt-1 text-sm text-ink-muted">Aşağıdaki hesaplardan birine <x-money :value="$balance" class="font-semibold text-ink" /> yatırıp dekontunuzu yükleyin.</p>

            <div class="mt-5 overflow-hidden rounded-xl border border-line">
                <div class="divide-y divide-line">
                    @foreach ($bankAccounts as $account)
                        <div class="px-4 py-2.5">
                            <p class="text-sm font-medium text-ink">{{ $account['bank'] }}</p>
                            <p class="text-[11px] text-ink-muted">{{ $account['branch'] ?? '' }}</p>
                            <p class="mt-0.5 font-mono text-xs text-ink">{{ $account['iban'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5">
                <label class="field-label">Banka dekontu <span class="text-red-500">*</span></label>
                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required
                       class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                @error('receipt') <p class="field-error">{{ $message }}</p> @enderror
                <p class="field-hint">Dekontunuz Yönetim tarafından doğrulandıktan sonra ödemeniz tamamlanmış sayılır.</p>
            </div>

            <button type="submit" class="btn-primary mt-6 w-full py-3">Dekontu Gönder</button>
        </form>
    </div>
</x-layouts.customer>
