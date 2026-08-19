<x-layouts.customer title="Bakiye Ödemesi">

    <div x-data="{ method: 'card', installment: {{ (int) (config('payment.installments')[0] ?? 1) }} }" class="mx-auto max-w-4xl">
        <a href="{{ route('customer.reservations.show', $reservation) }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Rezervasyona dön
        </a>

        {{-- Neyin ödendiğini görselle hatırlatır --}}
        <div class="relative mt-4 mb-6 overflow-hidden rounded-2xl">
            <img src="{{ $reservation->facility->coverUrl() }}" alt="{{ $reservation->facility->name }}"
                 class="h-40 w-full object-cover" loading="eager">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/70">Bakiye ödemesi · {{ $reservation->code }}</p>
                <h1 class="mt-1 font-display text-2xl font-semibold text-white">{{ $reservation->facility->name }}</h1>
                <p class="mt-0.5 text-sm text-white/80">
                    {{ $reservation->roomType->name }} ·
                    {{ $reservation->start_date->translatedFormat('d F') }} – {{ $reservation->end_date->translatedFormat('d F Y') }}
                </p>
            </div>
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
            </div>
        </div>

        {{-- Yöntem seçimi --}}
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
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
            <button type="button" @click="method = 'on_site'" class="choice-tile"
                    :class="method === 'on_site' ? 'choice-tile-active' : 'choice-tile-idle'">
                <span class="font-semibold text-ink">Tesiste ödeyeceğim</span>
                <span class="text-xs text-ink-muted">Bakiyeyi tesise girişte nakit veya kartla ödersiniz.</span>
            </button>
        </div>

        {{-- Tesiste ödeme --}}
        <div x-show="method === 'on_site'" x-cloak class="surface mb-6 p-6">
            <h2 class="font-display text-lg font-semibold text-ink">Tesiste ödeme</h2>
            <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                Bakiyenizi tesise girişte ödeyeceğinizi bildirirsiniz. Kaydınız korunur; tahsilat
                giriş sırasında resepsiyonda yapılır ve ödemeniz o anda işlenir.
            </p>
            <form method="POST" action="{{ route('customer.payment.on-site', $reservation) }}" class="mt-5">
                @csrf
                <button type="submit" class="btn-accent w-full">
                    Bakiyeyi tesiste ödeyeceğim
                </button>
            </form>
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
                            <x-iban :value="$account['iban']" />
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
