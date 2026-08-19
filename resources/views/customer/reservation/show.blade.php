<x-layouts.customer :title="'Rezervasyon ' . $reservation->code">

    <div x-data="{ cancelOpen: false }" class="mx-auto max-w-4xl">
        <a href="{{ route('customer.dashboard') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Panelime dön
        </a>

        {{-- Tesis görseli — rezervasyonu somutlaştırır --}}
        <div class="relative mt-4 mb-6 overflow-hidden rounded-2xl">
            <img src="{{ $reservation->facility->coverUrl() }}" alt="{{ $reservation->facility->name }}"
                 class="h-48 w-full object-cover sm:h-56" loading="eager">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/70">
                    {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->label() }}@endif
                </p>
                <h1 class="mt-1 font-display text-2xl font-semibold text-white">{{ $reservation->facility->name }}</h1>
                <p class="mt-0.5 text-sm text-white/80">
                    {{ $reservation->start_date->translatedFormat('d F') }} – {{ $reservation->end_date->translatedFormat('d F Y') }}
                    · {{ $reservation->nights }} gün
                </p>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="section-label">Rezervasyon</p>
                <h1 class="page-title mt-1">{{ $reservation->code }}</h1>
                <p class="page-subtitle">{{ $reservation->created_at->translatedFormat('d F Y H:i') }} tarihinde oluşturuldu</p>
            </div>
            <x-status-badge :status="$reservation->status"
                            :label="$reservation->collectsOnSite() ? 'Tesiste Ödeyecek' : null"
                            class="!px-3 !py-1.5 !text-sm" />
        </div>

        {{-- Durum açıklaması --}}
        @php
            $statusNote = match ($reservation->status) {
                'pending' => 'Müracaatınız değerlendiriliyor. Müracaat edilmesi ve peşinat yatırılması yer tahsisi yapılacağı anlamına gelmez.',
                'approved' => $reservation->collectsOnSite()
                    ? 'Rezervasyonunuz kesinleşti. Kalan bakiyeyi tesise girişte ödeyeceksiniz; başka bir işlem yapmanıza gerek yok.'
                    : 'Yer tahsisi yapıldı. Kalan bakiyeyi kartla, havaleyle ya da tesise girişte ödeyebilirsiniz.',
                'paid' => 'Ödemeniz tamamlandı. İyi tatiller dileriz.',
                'rejected' => 'Müracaatınız değerlendirme sonucunda uygun bulunmadı.',
                'cancelled' => 'Bu başvuru iptal edildi.',
                default => null,
            };
        @endphp

        <div class="alert-soft mb-6 border-line bg-surface text-ink ring-line">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-accent-600 dark:text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
            <p class="text-sm">{{ $statusNote }}</p>
        </div>

        {{-- Tesiste ödenecek --}}
        @if ($reservation->collectsOnSite() && $reservation->balanceDue() > 0)
            <div class="surface mb-6 overflow-hidden border-teal-300 dark:border-teal-800">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-ink-muted">Tesise girişte ödenecek</p>
                        <x-money :value="$reservation->balanceDue()" class="font-display text-2xl font-semibold text-ink" />
                        <p class="mt-1 text-xs text-ink-muted">
                            {{ $reservation->collect_on_site_at->translatedFormat('d F Y') }} tarihinde kesinleştirdiniz.
                            Nakit veya kartla ödeyebilirsiniz.
                        </p>
                    </div>
                    <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-secondary shrink-0">
                        Şimdi ödemek istiyorum
                    </a>
                </div>
            </div>

        {{-- Bakiye ödeme çağrısı --}}
        @elseif ($reservation->status === 'approved' && $reservation->balanceDue() > 0)
            <div class="surface mb-6 overflow-hidden border-accent-300 dark:border-accent-700">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm text-ink-muted">Ödenecek bakiye</p>
                        <x-money :value="$reservation->balanceDue()" class="font-display text-2xl font-semibold text-ink" />
                        @if ($reservation->admin_note)
                            <p class="mt-2 max-w-lg rounded-lg bg-surface-alt px-3 py-2 text-xs leading-relaxed text-ink">
                                <span class="font-semibold">Yönetim notu:</span> {{ $reservation->admin_note }}
                            </p>
                        @endif
                    </div>
                    <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-accent shrink-0">Ödemeye Geç</a>
                </div>
            </div>
        @endif

        {{-- Konaklama bilgileri --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-ink">Konaklama bilgileri</h2>
            </div>
            <div class="divide-y divide-line">
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-ink-muted">Tesis</span><span class="font-medium text-ink">{{ $reservation->facility->name }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-ink-muted">Oda tipi</span><span class="font-medium text-ink">{{ $reservation->roomType->name }}</span></div>
                @if ($reservation->room && in_array($reservation->status, ['approved', 'paid'], true))
                    <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-ink-muted">Odanız</span><span class="font-medium text-ink">{{ $reservation->room->label() }}</span></div>
                @endif
                <div class="flex justify-between gap-4 px-6 py-3.5 text-sm">
                    <span class="text-ink-muted">Devre</span>
                    <span class="text-right font-medium text-ink">
                        {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->label() }}@endif
                    </span>
                </div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-ink-muted">Tarih</span><span class="font-medium text-ink">{{ $reservation->start_date->translatedFormat('d F Y') }} – {{ $reservation->end_date->translatedFormat('d F Y') }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-ink-muted">Süre</span><span class="font-medium text-ink">{{ $reservation->nights }} gün</span></div>
                @if ($reservation->ground_floor_request)
                    <div class="flex justify-between gap-4 px-6 py-3.5 text-sm"><span class="text-ink-muted">Zemin kat talebi</span><span class="max-w-xs text-right font-medium text-ink">{{ $reservation->ground_floor_note }}</span></div>
                @endif
                @if ($reservation->note)
                    <div class="flex justify-between gap-4 px-6 py-3.5 text-sm"><span class="text-ink-muted">Notunuz</span><span class="max-w-xs text-right text-ink">{{ $reservation->note }}</span></div>
                @endif
            </div>
        </div>

        {{-- Kişiler --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-ink">Konaklayacak kişiler</h2>
            </div>
            <ul class="divide-y divide-line">
                @foreach ($reservation->guests as $guest)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                        <div class="min-w-0">
                            <p class="font-medium text-ink">{{ $guest->full_name }}</p>
                            <p class="text-xs text-ink-muted">
                                {{ $guest->maskedTcNo() }} · {{ $guest->relationLabel() }} ·
                                {{ $guest->customerGroup->name }} · {{ $guest->ageCategoryLabel() }}
                                @if ($guest->wants_meal) · yemek talepli @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-money :value="$guest->line_total" zero="Ücretsiz" class="text-sm font-semibold text-ink" />
                            @if ($guest->id_document_path)
                                <a href="{{ route('documents.identity', $guest) }}" target="_blank" rel="noopener"
                                   class="btn-ghost !px-2.5 !py-1.5 text-xs" title="Kimlik belgesini görüntüle">Kimlik</a>
                                @if ($guest->civil_registry_path)
                                    <a href="{{ route('documents.civil-registry', $guest) }}" target="_blank" rel="noopener"
                                       class="btn-ghost !px-2.5 !py-1.5 text-xs" title="Vukuatlı nüfus kaydını görüntüle">Nüfus kaydı</a>
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Ücret dökümü --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-ink">Ücret dökümü</h2>
            </div>
            <div class="divide-y divide-line">
                @if ($reservation->surcharge_per_person_day > 0)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-ink-muted">Müracaat tarihi farkı (kişi/gün)</span>
                        <x-money :value="$reservation->surcharge_per_person_day" class="font-medium text-ink" />
                    </div>
                @endif
                <div class="flex justify-between px-6 py-3 text-sm">
                    <span class="text-ink-muted">Konaklama</span>
                    <x-money :value="$reservation->accommodation_total" class="font-medium text-ink" />
                </div>
                @if ($reservation->empty_bed_total > 0)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-ink-muted">Boş yatak ücreti ({{ $reservation->empty_bed_count }} yatak × <x-money :value="$reservation->empty_bed_fee_per_day" /> × {{ $reservation->nights }} gün)</span>
                        <x-money :value="$reservation->empty_bed_total" class="font-medium text-ink" />
                    </div>
                @endif
                @if ((float) $reservation->adjustment_amount !== 0.0)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-ink-muted">{{ $reservation->adjustment_note ?: 'Yönetim düzeltmesi' }}</span>
                        <x-money :value="$reservation->adjustment_amount" class="font-medium text-ink" />
                    </div>
                @endif
                <div class="flex justify-between bg-surface-alt px-6 py-4">
                    <span class="font-semibold text-ink">Toplam tutar</span>
                    <x-money :value="$reservation->total_price" class="font-display text-xl font-semibold text-accent-700 dark:text-accent-300" />
                </div>
                <div class="flex justify-between px-6 py-3 text-sm">
                    <span class="text-ink-muted">Ödenen</span>
                    <x-money :value="$reservation->paidTotal()" class="font-medium text-ink" />
                </div>
                <div class="flex justify-between px-6 py-3 text-sm">
                    <span class="font-semibold text-ink-muted">
                        {{ $reservation->collectsOnSite() ? 'Tesiste ödenecek' : 'Kalan bakiye' }}
                    </span>
                    <x-money :value="$reservation->balanceDue()" class="font-semibold text-ink" />
                </div>
            </div>
        </div>

        {{--
            İade talebe bağlıdır: Dernek iadeleri belirli aralıklarla toplu ödediği için
            kayıt kendiliğinden açılmaz, üye istediğinde talep gönderir.
        --}}
        @if (! $reservation->refund && in_array($reservation->status, ['rejected', 'cancelled'], true) && $reservation->paidTotal() > 0)
            <div class="surface mb-6 p-6">
                <h2 class="font-display text-lg font-semibold text-ink">Peşinat iadesi</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                    Bu rezervasyon için <x-money :value="$reservation->paidTotal()" class="font-semibold text-ink" />
                    tahsil edilmişti. İadenizi almak istiyorsanız talep gönderin; iadeler Dernek tarafından
                    belirli aralıklarla toplu olarak ödenir.
                </p>
                <form method="POST" action="{{ route('customer.refunds.request', $reservation) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-primary">İade talebi gönder</button>
                </form>
            </div>
        @endif

        {{-- İade — talep gönderilmiş başvurularda --}}
        @if ($reservation->refund)
            @php $iade = $reservation->refund; @endphp

            <div class="surface mb-6 overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-lg font-semibold text-ink">
                                {{ $iade->reason === 'overpayment' ? 'İade' : 'Peşinat iadesi' }}
                            </h2>
                            <p class="text-xs text-ink-muted">{{ $iade->reasonLabel() }}</p>
                        </div>
                        <span class="badge-{{ $iade->isPaid() ? 'green' : ($iade->status === 'pending' ? 'accent' : 'amber') }}">
                            {{ $iade->statusLabel() }}
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-line">
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-ink-muted">Tahsil edilen</span>
                        <x-money :value="$iade->gross_amount" class="text-ink" />
                    </div>
                    @if ((float) $iade->deduction > 0)
                        <div class="flex justify-between px-6 py-3 text-sm">
                            <span class="text-ink-muted">Kırtasiye ve hizmet bedeli</span>
                            <span class="text-ink">− <x-money :value="$iade->deduction" /></span>
                        </div>
                    @endif
                    <div class="flex justify-between px-6 py-3.5 text-sm">
                        <span class="font-semibold text-ink-muted">İade edilecek</span>
                        <x-money :value="$iade->amount" class="font-semibold text-ink" />
                    </div>
                </div>

                <div class="border-t border-line p-6">
                    @if (! $iade->isPaid() && $iade->reason === 'overpayment')
                        {{-- Fazla ödeme iadesi taraflar arasında yapılır; IBAN istenmez --}}
                        <div class="alert-soft border-amber-200 bg-amber-50 text-amber-900 ring-amber-200 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100 dark:ring-amber-800">
                            <div>
                                <p class="font-semibold">
                                    <x-money :value="$iade->amount" /> tutarı iade edilecektir.
                                </p>
                                <p class="mt-1 text-xs leading-relaxed">
                                    Rezervasyonunuzdaki değişiklik sonrası oluşan fazla ödemedir.
                                    İade Dernek tarafından yapılacaktır; tamamlandığında bu sayfada
                                    "İade Edildi" olarak görünür.
                                </p>
                            </div>
                        </div>
                    @elseif ($iade->isPaid())
                        <div class="alert-soft border-teal-200 bg-teal-50 text-teal-800 ring-teal-200 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-200 dark:ring-teal-800">
                            <div>
                                <p class="font-semibold">İade {{ $iade->paid_at->translatedFormat('d F Y') }} tarihinde yapıldı.</p>
                                @if ($iade->iban)
                                    <p class="mt-1 text-xs">
                                        {{ $iade->ibanFormatted() }} · {{ $iade->account_holder }}
                                        @if ($iade->reference_no) · Referans {{ $iade->reference_no }} @endif
                                    </p>
                                @endif
                                <p class="mt-1 text-xs">Hesabınıza geçmediyse Dernek ile iletişime geçin.</p>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('customer.refunds.update', $iade) }}" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <p class="text-sm text-ink-muted">
                                @if ($iade->status === 'awaiting_iban')
                                    İadenin yapılabilmesi için hesap bilgilerinizi bildirin. Hesabın size ait olması gerekir.
                                @else
                                    Hesap bilgileriniz alındı; iade bu hesaba yapılacaktır. Gerekirse aşağıdan güncelleyebilirsiniz.
                                @endif
                            </p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="iban" class="field-label">IBAN</label>
                                    <input id="iban" type="text" name="iban" maxlength="34" required
                                           value="{{ old('iban', $iade->ibanFormatted()) }}"
                                           placeholder="TR00 0000 0000 0000 0000 0000 00"
                                           class="field-input font-mono">
                                    @error('iban') <p class="field-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="account_holder" class="field-label">Hesap sahibi</label>
                                    <input id="account_holder" type="text" name="account_holder" maxlength="120" required
                                           value="{{ old('account_holder', $iade->account_holder ?? auth()->user()->name) }}"
                                           class="field-input">
                                    @error('account_holder') <p class="field-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <button type="submit" class="btn-primary">
                                {{ $iade->status === 'awaiting_iban' ? 'Hesap bilgilerimi bildir' : 'Hesap bilgilerimi güncelle' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Ödemeler --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-ink">Ödemeler</h2>
            </div>
            @if ($reservation->payments->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-ink-subtle">Henüz ödeme kaydı bulunmuyor.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($reservation->payments->sortBy('created_at') as $payment)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                            <div>
                                <p class="text-sm font-medium text-ink">
                                    {{ $payment->kindLabel() }} · {{ $payment->methodLabel() }}
                                    @if ($payment->installment > 1) · {{ $payment->installment }} taksit @endif
                                </p>
                                <p class="text-xs text-ink-muted">
                                    {{ $payment->reference_no }} ·
                                    {{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('d F Y H:i') }}
                                    @if ($payment->failure_reason) · {{ $payment->failure_reason }} @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($payment->receipt_path)
                                    <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener" class="btn-ghost !px-2.5 !py-1.5 text-xs">Dekont</a>
                                @endif
                                <x-money :value="$payment->amount" class="text-sm font-semibold text-ink" />
                                <x-status-badge :status="$payment->status" :label="$payment->statusLabel()" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Yönetici notu --}}
        @if ($reservation->admin_note)
            <div class="surface mb-6 px-6 py-5">
                <p class="section-label">Yönetim notu</p>
                <p class="mt-2 whitespace-pre-line text-sm text-ink-muted">{{ $reservation->admin_note }}</p>
            </div>
        @endif

        {{-- İptal --}}
        @if ($reservation->isCancellable())
            <button type="button" @click="cancelOpen = true" class="btn-secondary w-full !text-red-600">Rezervasyonu iptal et</button>

            <template x-teleport="body">
                <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="modal-scrim" @click="cancelOpen = false"></div>
                    <div class="modal-panel" x-transition>
                        <h3 class="font-display text-lg font-semibold text-ink">Rezervasyonu iptal et</h3>

                        @php
                            $odenenTutar = $reservation->paidTotal();
                            $iptalKesintisi = min($odenenTutar, app(\App\Services\RefundService::class)
                                ->deductionFor($reservation, 'cancelled'));
                            $iptalIadesi = round($odenenTutar - $iptalKesintisi, 2);
                        @endphp

                        @if ($odenenTutar > 0)
                            <div class="mt-3 overflow-hidden rounded-xl border border-line text-sm">
                                <div class="flex justify-between px-4 py-2.5">
                                    <span class="text-ink-muted">Ödediğiniz tutar</span>
                                    <x-money :value="$odenenTutar" class="font-medium text-ink" />
                                </div>
                                <div class="flex justify-between border-t border-line px-4 py-2.5">
                                    <span class="text-ink-muted">
                                        Kesinti
                                        @if ($reservation->isLateCancel())
                                            <span class="block text-[11px]" style="color: var(--status-warn)">
                                                Devre başlangıcına 10 günden az kaldığı için konaklama bedelinin üçte biri
                                            </span>
                                        @else
                                            <span class="block text-[11px] text-ink-subtle">Kırtasiye ve hizmet bedeli</span>
                                        @endif
                                    </span>
                                    <span class="font-medium text-ink">− <x-money :value="$iptalKesintisi" /></span>
                                </div>
                                <div class="flex justify-between border-t border-line bg-surface-alt px-4 py-2.5">
                                    <span class="font-semibold text-ink">İade edilecek</span>
                                    <x-money :value="$iptalIadesi" class="font-semibold text-ink" />
                                </div>
                            </div>
                        @else
                            <p class="mt-1 text-sm text-ink-muted">Bu rezervasyon için tahsil edilmiş bir tutar bulunmuyor.</p>
                        @endif
                        <form method="POST" action="{{ route('customer.reservations.cancel', $reservation) }}" class="mt-4">
                            @csrf
                            <textarea name="reason" rows="3" class="field-input" placeholder="İptal gerekçeniz (opsiyonel)"></textarea>
                            <div class="mt-4 flex gap-3">
                                <button type="button" @click="cancelOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                                <button type="submit" class="btn-danger flex-1">İptal Et</button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        @endif
    </div>
</x-layouts.customer>
