<x-layouts.customer title="Yeni Rezervasyon">

    <div x-data="reservationWizard()" class="mx-auto max-w-3xl">

        <div class="mb-8">
            <a href="{{ route('customer.dashboard') }}" class="back-link">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                Panelime dön
            </a>
            <p class="section-label mt-4">Yeni talep</p>
            <h1 class="page-title mt-1">Rezervasyon oluştur</h1>
            <p class="page-subtitle">Tarih, tesis ve bilgilerinizi adım adım tamamlayın.</p>
        </div>

        <!-- Stepper -->
        <div class="mb-8">
            <ol class="flex items-center gap-2">
                <template x-for="(label, idx) in stepLabels" :key="idx">
                    <li class="flex flex-1 items-center gap-2">
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2 sm:flex-row sm:items-center">
                            <div
                                class="stepper-dot"
                                :class="step > idx + 1 ? 'bg-teal-500 text-white' : (step === idx + 1 ? 'bg-navy-900 text-white shadow-soft scale-105' : 'bg-white text-slate-400 ring-1 ring-slate-200')"
                            >
                                <template x-if="step > idx + 1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                </template>
                                <span x-show="step <= idx + 1" x-text="idx + 1"></span>
                            </div>
                            <span class="hidden truncate text-xs font-semibold sm:block"
                                  :class="step === idx + 1 ? 'text-navy-900' : 'text-slate-400'"
                                  x-text="label"></span>
                        </div>
                        <div class="hidden h-px flex-1 rounded bg-slate-200 sm:block" :class="step > idx + 1 ? '!bg-teal-400' : ''" x-show="idx < stepLabels.length - 1"></div>
                    </li>
                </template>
            </ol>
            <p class="mt-4 text-sm font-semibold text-teal-700 sm:hidden" x-text="'Adım ' + step + ': ' + stepLabels[step - 1]"></p>
        </div>

        <form method="POST" action="{{ route('customer.reservations.store') }}" @submit="submitting = true">
            @csrf
            <input type="hidden" name="facility_id" :value="facilityId">
            <input type="hidden" name="check_in" :value="checkIn">
            <input type="hidden" name="check_out" :value="checkOut">
            <input type="hidden" name="guests" :value="guests">
            <input type="hidden" name="note" :value="note">

            @if ($errors->any())
                <div class="alert-soft mb-6 border-red-200 bg-red-50 text-red-700 ring-red-200">
                    <p class="font-medium">{{ $errors->first() }}</p>
                </div>
            @endif

            <div class="surface p-6 sm:p-8">

                <!-- Adım 1 -->
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <h2 class="font-display text-xl font-semibold text-navy-900">Konaklama tarihleri</h2>
                    <p class="mt-1 text-sm text-slate-500">Giriş ve çıkış tarihlerinizi seçin.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Giriş tarihi</label>
                            <input type="date" x-model="checkIn" :min="today" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Çıkış tarihi</label>
                            <input type="date" x-model="checkOut" :min="checkIn || today" class="field-input">
                        </div>
                    </div>
                    <p class="mt-4 inline-flex items-center gap-2 rounded-lg bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 ring-1 ring-teal-100"
                       x-show="nights > 0" x-cloak x-text="nights + ' gecelik konaklama seçildi'"></p>
                </div>

                <!-- Adım 2 -->
                <div x-show="step === 2" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <h2 class="font-display text-xl font-semibold text-navy-900">Tesis seçimi</h2>
                    <p class="mt-1 text-sm text-slate-500">Uygun sosyal tesisi seçin.</p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($facilities as $f)
                            <button
                                type="button"
                                @click="facilityId = {{ $f->id }}; facilityName = @js($f->name)"
                                class="choice-tile"
                                :class="facilityId === {{ $f->id }} ? 'choice-tile-active' : 'choice-tile-idle'"
                            >
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-900/5 text-teal-700">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                </span>
                                <span class="mt-2 font-semibold text-navy-900">{{ $f->name }}</span>
                                <span class="text-xs text-slate-500">{{ $f->location }} · Kapasite {{ $f->capacity }} kişi</span>
                                <span class="mt-1 text-xs leading-relaxed text-slate-400 line-clamp-2">{{ $f->description }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Adım 3 -->
                <div x-show="step === 3" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <h2 class="font-display text-xl font-semibold text-navy-900">Rezervasyon bilgileri</h2>
                    <p class="mt-1 text-sm text-slate-500">Kişi sayısı ve varsa özel notunuzu girin.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Kişi sayısı</label>
                            <input type="number" x-model.number="guests" min="1" max="20" class="field-input">
                        </div>
                    </div>
                    <div class="mt-5">
                        <label class="field-label">Ek not <span class="font-normal text-slate-400">(opsiyonel)</span></label>
                        <textarea x-model="note" rows="3" class="field-input" placeholder="Varsa özel talebinizi belirtin"></textarea>
                    </div>
                </div>

                <!-- Adım 4: Özet + gönder -->
                <div x-show="step === 4" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <h2 class="font-display text-xl font-semibold text-navy-900">Özet ve gönderim</h2>
                    <p class="mt-1 text-sm text-slate-500">Bilgilerinizi kontrol edin ve talebinizi gönderin.</p>

                    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200/80">
                        <div class="flex justify-between px-4 py-3 text-sm"><span class="text-slate-500">Tesis</span><span class="font-medium text-navy-900" x-text="facilityName"></span></div>
                        <div class="flex justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Tarih aralığı</span><span class="font-medium text-navy-900" x-text="checkIn + ' — ' + checkOut"></span></div>
                        <div class="flex justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Süre</span><span class="font-medium text-navy-900" x-text="nights + ' gece'"></span></div>
                        <div class="flex justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Kişi</span><span class="font-medium text-navy-900" x-text="guests"></span></div>
                        <div class="flex justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Sınıf</span><span class="font-medium text-navy-900">{{ $customerClass->name }} (₺{{ number_format($customerClass->daily_price, 0, ',', '.') }}/gece)</span></div>
                        <div class="flex justify-between border-t border-slate-100 bg-sand-50 px-4 py-4"><span class="font-semibold text-navy-900">Toplam tutar</span><span class="font-display text-xl font-semibold text-teal-700" x-text="'₺' + totalPrice.toLocaleString('tr-TR')"></span></div>
                    </div>
                    <p class="field-hint mt-3">Bu tutar tahmindir. Admin onayının ardından ödeme adımında kesinleşir.</p>
                    <div class="mt-5 alert-soft border-teal-100 bg-teal-50/70 text-teal-900 ring-teal-100">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <p>Talebiniz incelendikten sonra onaylanacak veya reddedilecektir. Onaylanırsa ödeme seçenekleri açılır.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3">
                <button type="button" @click="step--" x-show="step > 1" x-cloak class="btn-secondary">Geri</button>
                <span x-show="step === 1"></span>

                <button type="button" @click="step++" x-show="step < 4" :disabled="!canProceed" class="btn-primary min-w-[7.5rem]">İleri</button>
                <button type="submit" x-show="step === 4" x-cloak :disabled="submitting" class="btn-accent min-w-[10rem]">
                    <span x-show="!submitting">Talebi Gönder</span>
                    <span x-show="submitting" x-cloak>Gönderiliyor…</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        function reservationWizard() {
            const dailyPrice = {{ (float) $customerClass->daily_price }};
            return {
                step: 1,
                submitting: false,
                today: new Date().toISOString().split('T')[0],
                stepLabels: ['Tarih', 'Tesis', 'Bilgiler', 'Özet'],
                checkIn: '',
                checkOut: '',
                facilityId: null,
                facilityName: '',
                guests: 1,
                note: '',
                get nights() {
                    if (!this.checkIn || !this.checkOut) return 0;
                    const d = (new Date(this.checkOut) - new Date(this.checkIn)) / (1000 * 60 * 60 * 24);
                    return d > 0 ? Math.round(d) : 0;
                },
                get totalPrice() {
                    return this.nights * dailyPrice;
                },
                get canProceed() {
                    if (this.step === 1) return this.checkIn && this.checkOut && this.nights > 0;
                    if (this.step === 2) return !!this.facilityId;
                    if (this.step === 3) return this.guests >= 1;
                    return true;
                },
            };
        }
    </script>
</x-layouts.customer>
