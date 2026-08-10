<x-layouts.admin title="Rezervasyon Detayı">

    <div x-data="{ rejectOpen: false }" class="mx-auto max-w-2xl">
        <a href="{{ route('admin.reservations.index') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Rezervasyonlar
        </a>

        <div class="mt-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="section-label">Rezervasyon</p>
                <h1 class="page-title mt-1">#{{ $reservation->id }}</h1>
            </div>
            <x-status-badge :status="$reservation->status" />
        </div>

        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-slate-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Müşteri bilgileri</h2>
            </div>
            <div class="divide-y divide-slate-100/80">
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Ad soyad</span><span class="font-medium text-navy-900">{{ $reservation->user->name }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">TC kimlik no</span><span class="font-medium text-navy-900">{{ $reservation->user->maskedTcNo() }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Telefon</span><span class="font-medium text-navy-900">{{ $reservation->user->phone ?? '-' }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Müşteri sınıfı</span><span class="font-medium text-navy-900">{{ $reservation->customerClass->name }}</span></div>
            </div>
        </div>

        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-slate-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Rezervasyon bilgileri</h2>
            </div>
            <div class="divide-y divide-slate-100/80">
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Tesis</span><span class="font-medium text-navy-900">{{ $reservation->facility->name }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Tarih aralığı</span><span class="font-medium text-navy-900">{{ $reservation->check_in->format('d.m.Y') }} — {{ $reservation->check_out->format('d.m.Y') }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Konaklama</span><span class="font-medium text-navy-900">{{ $reservation->nights() }} gece</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-slate-500">Kişi sayısı</span><span class="font-medium text-navy-900">{{ $reservation->guests }}</span></div>
                @if ($reservation->note)
                    <div class="flex justify-between gap-6 px-6 py-3.5 text-sm"><span class="text-slate-500">Müşteri notu</span><span class="max-w-xs text-right font-medium text-navy-900">{{ $reservation->note }}</span></div>
                @endif
                <div class="flex justify-between bg-sand-50 px-6 py-4"><span class="font-semibold text-navy-900">Toplam tutar</span><span class="font-display text-xl font-semibold text-teal-700">₺{{ number_format($reservation->total_price, 0, ',', '.') }}</span></div>
            </div>
        </div>

        @if ($reservation->status === 'paid' && $reservation->payment)
            <div class="surface mb-6 px-6 py-5">
                <p class="section-label">Ödeme bilgisi</p>
                <p class="mt-2 text-sm text-slate-600">Yöntem: {{ $reservation->payment->method === 'credit_card' ? 'Kredi/Banka Kartı' : 'Havale/EFT' }}</p>
                <p class="text-sm text-slate-600">Referans: {{ $reservation->payment->reference_no }}</p>
                <p class="text-sm text-slate-600">Tarih: {{ $reservation->payment->paid_at->format('d.m.Y H:i') }}</p>
            </div>
        @endif

        @if ($reservation->admin_note)
            <div class="surface mb-6 px-6 py-5">
                <p class="section-label">Yönetici notu</p>
                <p class="mt-2 text-sm text-slate-600">{{ $reservation->admin_note }}</p>
            </div>
        @endif

        @if ($reservation->status === 'pending')
            <div class="flex flex-col gap-3 sm:flex-row">
                <form method="POST" action="{{ route('admin.reservations.approve', $reservation) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="btn-accent w-full py-3">Rezervasyonu Onayla</button>
                </form>
                <button type="button" @click="rejectOpen = true" class="btn-danger flex-1 py-3">Rezervasyonu Reddet</button>
            </div>

            <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="rejectOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-navy-900">Rezervasyonu reddet</h3>
                    <p class="mt-1 text-sm text-slate-500">Müşteriye gösterilecek bir açıklama girin.</p>
                    <form method="POST" action="{{ route('admin.reservations.reject', $reservation) }}" class="mt-4">
                        @csrf
                        <textarea name="admin_note" required rows="3" class="field-input" placeholder="Red gerekçesi…"></textarea>
                        <div class="mt-4 flex gap-3">
                            <button type="button" @click="rejectOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-danger flex-1">Reddet</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
