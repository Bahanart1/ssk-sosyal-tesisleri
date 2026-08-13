<x-layouts.customer title="Panelim">

    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="section-label">Hoş geldiniz</p>
            <h1 class="page-title mt-1">{{ $user->name }}</h1>
            <p class="page-subtitle">
                {{ $user->customerGroup?->name ?? 'Grup atanmadı' }}
                @if ($user->membership_no)
                    · Üyelik no {{ $user->membership_no }}
                @endif
            </p>
        </div>

        @if (! $hasDuesDebt && $user->customer_group_id)
            <a href="{{ route('customer.reservations.create') }}" class="btn-accent shrink-0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Yeni Başvuru
            </a>
        @endif
    </div>

    {{-- Aidat borcu uyarısı (Madde 5/10) --}}
    @if ($hasDuesDebt)
        <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
            <div>
                <p class="font-semibold">Aidat borcunuz bulunuyor</p>
                <p class="mt-0.5 text-sm">
                    İçinde bulunulan yıl dahil önceki yıllara ait aidat borcu bulunan üyelerin müracaat formları,
                    borç ödenmediği sürece işleme alınmaz. Borcunuzu ödedikten sonra Dernek ile iletişime geçerek
                    kaydınızı güncelletebilirsiniz.
                </p>
                @if ($user->dues_paid_year)
                    <p class="mt-1 text-xs text-amber-700">Kayıtlarımızda aidatınız {{ $user->dues_paid_year }} yılına kadar ödenmiş görünüyor.</p>
                @endif
            </div>
        </div>
    @elseif (! $user->customer_group_id)
        <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200">
            <p>Hesabınıza henüz bir müşteri grubu atanmamış. Başvuru yapabilmek için Dernek ile iletişime geçin.</p>
        </div>
    @endif

    {{-- Ödeme bekleyen başvurular --}}
    @foreach ($awaitingPayment as $reservation)
        <div class="surface mb-6 overflow-hidden border-teal-200/70">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="section-label">Yer tahsis edildi</p>
                    <p class="mt-1 font-display text-lg font-semibold text-navy-900">
                        {{ $reservation->facility->name }} · {{ $reservation->period->label() }}
                    </p>
                    <p class="mt-0.5 text-sm text-stone-500">
                        Bakiye: <x-money :value="$reservation->balanceDue()" class="font-semibold text-navy-800" />
                        @if ($reservation->balance_due_date)
                            · Son ödeme {{ $reservation->balance_due_date->translatedFormat('d F Y') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-accent shrink-0">Bakiyeyi Öde</a>
            </div>
        </div>
    @endforeach

    {{-- Başvurular --}}
    <div class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-stone-100/80 px-6 py-4">
            <h2 class="font-display text-lg font-semibold text-navy-900">Başvurularım</h2>
            <span class="text-xs text-stone-400">{{ $reservations->count() }} kayıt</span>
        </div>

        @if ($reservations->isEmpty())
            <div class="empty-state">
                <svg class="h-10 w-10 text-stone-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                <p class="font-medium text-stone-500">Henüz bir başvurunuz yok.</p>
                @if (! $hasDuesDebt && $user->customer_group_id)
                    <a href="{{ route('customer.reservations.create') }}" class="btn-primary mt-2">İlk başvurunuzu oluşturun</a>
                @endif
            </div>
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($reservations as $reservation)
                    <li>
                        <a href="{{ route('customer.reservations.show', $reservation) }}"
                           class="flex items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-teal-50/30">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-navy-900">{{ $reservation->facility->name }}</p>
                                    <x-status-badge :status="$reservation->status" />
                                </div>
                                <p class="mt-1 text-xs text-stone-500">
                                    {{ $reservation->code }} · {{ $reservation->roomType->name }} ·
                                    {{ $reservation->start_date->translatedFormat('d M') }} – {{ $reservation->end_date->translatedFormat('d M Y') }}
                                    ({{ $reservation->nights }} gün)
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <x-money :value="$reservation->total_price" class="font-display text-base font-semibold text-navy-900" />
                                @if ($reservation->balanceDue() > 0 && $reservation->status === 'approved')
                                    <p class="text-[11px] font-medium text-teal-700">Bakiye bekliyor</p>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="mt-6 text-center text-xs leading-relaxed text-stone-400">
        Tesislerden yararlanma koşulları, Dernek Yönetim Kurulunca belirlenen
        <span class="font-medium text-stone-500">Kamp Konaklama Usul ve Esasları</span>'na tabidir.
    </p>
</x-layouts.customer>
