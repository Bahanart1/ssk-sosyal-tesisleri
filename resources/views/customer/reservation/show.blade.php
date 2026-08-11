<x-layouts.customer title="Rezervasyon Detayı">

    <div class="mx-auto max-w-2xl">
        <a href="{{ route('customer.dashboard') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Panelime dön
        </a>

        @if (session('success') && $reservation->status === 'pending')
            <div class="surface mt-6 mb-6 overflow-hidden animate-rise">
                <div class="bg-gradient-to-br from-teal-50 to-white px-6 py-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-500 text-white shadow-glow">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <h1 class="mt-4 font-display text-2xl font-semibold text-navy-900">Talebiniz alındı</h1>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-stone-500">Admin onayı bekleniyor. İnceleme tamamlandığında bu sayfadan durumu takip edebilirsiniz.</p>
                </div>
            </div>
        @else
            <p class="section-label mt-4">Rezervasyon</p>
            <h1 class="page-title mt-1 mb-6">Detay</h1>
        @endif

        <div class="surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">{{ $reservation->facility->name }}</h2>
                <x-status-badge :status="$reservation->status" />
            </div>

            <div class="divide-y divide-stone-100/80">
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Tarih aralığı</span><span class="font-medium text-navy-900">{{ $reservation->check_in->translatedFormat('d M Y') }} — {{ $reservation->check_out->translatedFormat('d M Y') }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Kamp süresi</span><span class="font-medium text-navy-900">{{ $reservation->nights() }} gece (1 hafta)</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Kişi sayısı</span><span class="font-medium text-navy-900">{{ $reservation->guests }}</span></div>
                <div class="flex justify-between px-6 py-3.5 text-sm"><span class="text-stone-500">Müşteri sınıfı</span><span class="font-medium text-navy-900">{{ $reservation->customerClass->name }}</span></div>
                @if ($reservation->note)
                    <div class="flex justify-between gap-6 px-6 py-3.5 text-sm"><span class="text-stone-500">Notunuz</span><span class="max-w-xs text-right font-medium text-navy-900">{{ $reservation->note }}</span></div>
                @endif
                @if ($reservation->admin_note && in_array($reservation->status, ['rejected', 'approved']))
                    <div class="flex justify-between gap-6 px-6 py-3.5 text-sm"><span class="text-stone-500">Yönetici notu</span><span class="max-w-xs text-right font-medium text-navy-900">{{ $reservation->admin_note }}</span></div>
                @endif
                <div class="flex justify-between bg-sand-50 px-6 py-4"><span class="font-semibold text-navy-900">Toplam tutar</span><span class="font-display text-xl font-semibold text-teal-700">₺{{ number_format($reservation->total_price, 0, ',', '.') }}</span></div>
            </div>
        </div>

        @if ($reservation->status === 'approved')
            <div class="surface mt-6 overflow-hidden text-center">
                <div class="bg-gradient-to-br from-teal-50 via-white to-sand-50 px-6 py-8">
                    <p class="font-display text-xl font-semibold text-navy-900">Rezervasyonunuz onaylandı</p>
                    <p class="mx-auto mt-1.5 max-w-sm text-sm text-stone-500">Yerinizi kesinleştirmek için ödemenizi tamamlayabilirsiniz.</p>
                    <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-accent mt-5">Ödemeye Geç</a>
                </div>
            </div>
        @elseif ($reservation->status === 'paid' && $reservation->payment)
            <div class="surface mt-6 px-6 py-5">
                <p class="section-label">Ödeme bilgisi</p>
                <p class="mt-2 text-sm text-stone-600">Referans No: <span class="font-semibold text-navy-800">{{ $reservation->payment->reference_no }}</span></p>
                <p class="text-sm text-stone-600">Ödeme tarihi: {{ $reservation->payment->paid_at->translatedFormat('d M Y H:i') }}</p>
            </div>
        @elseif ($reservation->status === 'pending')
            <div class="alert-soft mt-6 border-amber-200 bg-amber-50 text-amber-800 ring-amber-200">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <p>Talebiniz admin onayı bekliyor. Onaylandığında bu sayfadan bilgilendirileceksiniz.</p>
            </div>
        @elseif ($reservation->status === 'rejected')
            <div class="alert-soft mt-6 border-red-200 bg-red-50 text-red-700 ring-red-200">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <p>Bu rezervasyon talebi reddedildi.</p>
            </div>
        @endif
    </div>
</x-layouts.customer>
