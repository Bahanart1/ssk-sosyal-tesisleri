<x-layouts.customer :title="'Başvuru ' . $reservation->code">

    <div x-data="{ cancelOpen: false }" class="mx-auto max-w-3xl">
        <a href="{{ route('customer.dashboard') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Panelime dön
        </a>

        <div class="mt-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="section-label">Başvuru</p>
                <h1 class="page-title mt-1">{{ $reservation->code }}</h1>
                <p class="page-subtitle">{{ $reservation->created_at->translatedFormat('d F Y H:i') }} tarihinde oluşturuldu</p>
            </div>
            <x-status-badge :status="$reservation->status" class="!px-3 !py-1.5 !text-sm" />
        </div>

        {{-- Durum açıklaması --}}
        @php
            $statusNote = match ($reservation->status) {
                'pending' => 'Müracaatınız değerlendiriliyor. Müracaat edilmesi ve peşinat yatırılması yer tahsisi yapılacağı anlamına gelmez.',
                'approved' => 'Yer tahsisi yapıldı. Bakiyeyi belirtilen tarihe kadar ödemeniz gerekmektedir.',
                'paid' => 'Ödemeniz tamamlandı. İyi tatiller dileriz.',
                'rejected' => 'Müracaatınız değerlendirme sonucunda uygun bulunmadı.',
                'cancelled' => 'Bu başvuru iptal edildi.',
                default => null,
            };
        @endphp

        <div class="alert-soft mb-6 border-stone-200 bg-white/70 text-stone-700 ring-stone-200">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
            <p class="text-sm">{{ $statusNote }}</p>
        </div>

        {{-- Bakiye ödeme çağrısı --}}
        @if ($reservation->status === 'approved' && $reservation->balanceDue() > 0)
            <div class="surface mb-6 overflow-hidden border-teal-200/70">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-stone-500">Ödenecek bakiye</p>
                        <x-money :value="$reservation->balanceDue()" class="font-display text-2xl font-semibold text-navy-900" />
                        @if ($reservation->balance_due_date)
                            <p class="mt-0.5 text-xs text-stone-500">Son ödeme tarihi: {{ $reservation->balance_due_date->translatedFormat('d F Y') }}</p>
                        @endif
                    </div>
                    <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-accent shrink-0">Ödemeye Geç</a>
                </div>
            </div>
        @endif

        {{-- Konaklama bilgileri --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Konaklama bilgileri</h2>
            </div>
            <div class="divide-y divide-stone-100/80">
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Tesis</span><span class="font-medium text-navy-900">{{ $reservation->facility->name }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Oda tipi</span><span class="font-medium text-navy-900">{{ $reservation->roomType->name }}</span></div>
                <div class="flex justify-between gap-4 px-6 py-3.5 text-sm">
                    <span class="text-stone-500">Devre</span>
                    <span class="text-right font-medium text-navy-900">
                        {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->label() }}@endif
                    </span>
                </div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Tarih</span><span class="font-medium text-navy-900">{{ $reservation->start_date->translatedFormat('d F Y') }} – {{ $reservation->end_date->translatedFormat('d F Y') }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Süre</span><span class="font-medium text-navy-900">{{ $reservation->nights }} gün</span></div>
                @if ($reservation->ground_floor_request)
                    <div class="flex justify-between gap-4 px-6 py-3.5 text-sm"><span class="text-stone-500">Zemin kat talebi</span><span class="max-w-xs text-right font-medium text-navy-900">{{ $reservation->ground_floor_note }}</span></div>
                @endif
                @if ($reservation->note)
                    <div class="flex justify-between gap-4 px-6 py-3.5 text-sm"><span class="text-stone-500">Notunuz</span><span class="max-w-xs text-right text-navy-900">{{ $reservation->note }}</span></div>
                @endif
            </div>
        </div>

        {{-- Kişiler --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Konaklayacak kişiler</h2>
            </div>
            <ul class="divide-y divide-stone-100/80">
                @foreach ($reservation->guests as $guest)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                        <div class="min-w-0">
                            <p class="font-medium text-navy-900">{{ $guest->full_name }}</p>
                            <p class="text-xs text-stone-500">
                                {{ $guest->maskedTcNo() }} · {{ $guest->relationLabel() }} ·
                                {{ $guest->customerGroup->name }} · {{ $guest->ageCategoryLabel() }}
                                @if ($guest->wants_meal) · yemek talepli @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-money :value="$guest->line_total" zero="Ücretsiz" class="text-sm font-semibold text-navy-800" />
                            @if ($guest->id_document_path)
                                <a href="{{ route('documents.identity', $guest) }}" target="_blank" rel="noopener"
                                   class="btn-ghost !px-2.5 !py-1.5 text-xs" title="Kimlik belgesini görüntüle">Belge</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Ücret dökümü --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Ücret dökümü</h2>
            </div>
            <div class="divide-y divide-stone-100/80">
                @if ($reservation->surcharge_per_person_day > 0)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-stone-500">Müracaat tarihi farkı (kişi/gün)</span>
                        <x-money :value="$reservation->surcharge_per_person_day" class="font-medium text-navy-900" />
                    </div>
                @endif
                <div class="flex justify-between px-6 py-3 text-sm">
                    <span class="text-stone-500">Konaklama</span>
                    <x-money :value="$reservation->accommodation_total" class="font-medium text-navy-900" />
                </div>
                @if ($reservation->empty_bed_total > 0)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-stone-500">Boş yatak ücreti ({{ $reservation->empty_bed_count }} yatak × <x-money :value="$reservation->empty_bed_fee_per_day" /> × {{ $reservation->nights }} gün)</span>
                        <x-money :value="$reservation->empty_bed_total" class="font-medium text-navy-900" />
                    </div>
                @endif
                @if ((float) $reservation->adjustment_amount !== 0.0)
                    <div class="flex justify-between px-6 py-3 text-sm">
                        <span class="text-stone-500">{{ $reservation->adjustment_note ?: 'Yönetim düzeltmesi' }}</span>
                        <x-money :value="$reservation->adjustment_amount" class="font-medium text-navy-900" />
                    </div>
                @endif
                <div class="flex justify-between bg-sand-50 px-6 py-4">
                    <span class="font-semibold text-navy-900">Toplam tutar</span>
                    <x-money :value="$reservation->total_price" class="font-display text-xl font-semibold text-teal-700" />
                </div>
                <div class="flex justify-between px-6 py-3 text-sm">
                    <span class="text-stone-500">Ödenen</span>
                    <x-money :value="$reservation->paidTotal()" class="font-medium text-navy-900" />
                </div>
                <div class="flex justify-between px-6 py-3 text-sm">
                    <span class="font-semibold text-stone-600">Kalan bakiye</span>
                    <x-money :value="$reservation->balanceDue()" class="font-semibold text-navy-900" />
                </div>
            </div>
        </div>

        {{-- Ödemeler --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Ödemeler</h2>
            </div>
            @if ($reservation->payments->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-stone-400">Henüz ödeme kaydı bulunmuyor.</p>
            @else
                <ul class="divide-y divide-stone-100/80">
                    @foreach ($reservation->payments->sortBy('created_at') as $payment)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                            <div>
                                <p class="text-sm font-medium text-navy-900">
                                    {{ $payment->kindLabel() }} · {{ $payment->methodLabel() }}
                                    @if ($payment->installment > 1) · {{ $payment->installment }} taksit @endif
                                </p>
                                <p class="text-xs text-stone-500">
                                    {{ $payment->reference_no }} ·
                                    {{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('d F Y H:i') }}
                                    @if ($payment->failure_reason) · {{ $payment->failure_reason }} @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($payment->receipt_path)
                                    <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener" class="btn-ghost !px-2.5 !py-1.5 text-xs">Dekont</a>
                                @endif
                                <x-money :value="$payment->amount" class="text-sm font-semibold text-navy-800" />
                                <x-status-badge :status="$payment->status" />
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
                <p class="mt-2 whitespace-pre-line text-sm text-stone-600">{{ $reservation->admin_note }}</p>
            </div>
        @endif

        {{-- İptal --}}
        @if ($reservation->isCancellable())
            <button type="button" @click="cancelOpen = true" class="btn-secondary w-full !text-red-600">Başvuruyu iptal et</button>

            <template x-teleport="body">
                <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="modal-scrim" @click="cancelOpen = false"></div>
                    <div class="modal-panel" x-transition>
                        <h3 class="font-display text-lg font-semibold text-navy-900">Başvuruyu iptal et</h3>
                        <p class="mt-1 text-sm text-stone-500">
                            Ödediğiniz tutarın iadesi, Yönetim Kurulunca belirlenen kırtasiye ve hizmet bedeli
                            düşülerek yapılır.
                        </p>
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
