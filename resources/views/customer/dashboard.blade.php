<x-layouts.customer title="Panelim">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-label">Hoş geldiniz</p>
            <h1 class="page-title mt-1">{{ $user->name }}</h1>
            <p class="page-subtitle">
                {{ $user->customerGroup?->name ?? 'Grup atanmadı' }}
                @if ($user->membership_no) · Üyelik no {{ $user->membership_no }} @endif
            </p>
        </div>

        @if ($canApply)
            <a href="{{ route('customer.reservations.create') }}" class="btn-accent shrink-0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Yeni Başvuru
            </a>
        @endif
    </div>

    {{-- Başvuru engelleri --}}
    @if ($hasDuesDebt)
        <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100 dark:ring-amber-800">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">
                    Aidat borcunuz var — <x-money :value="$duesDebtTotal" />
                </p>
                <p class="mt-1 text-sm">
                    Borç ödenmediği sürece müracaat formunuz işleme alınmaz.
                    Borçlu yıllar: {{ $outstandingDues->pluck('year')->implode(', ') }}.
                </p>
                <a href="{{ route('customer.dues.index') }}" class="mt-2 inline-flex text-sm font-semibold underline">
                    Aidat detaylarını görüntüle →
                </a>
            </div>
        </div>
    @elseif (! $user->customer_group_id)
        <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100 dark:ring-amber-800">
            <p>Hesabınıza henüz bir müşteri grubu atanmamış. Başvuru yapabilmek için Dernek ile iletişime geçin.</p>
        </div>
    @endif

    {{-- Durum kartları --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('customer.dues.index') }}" class="stat-card surface-hover block">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Aidat durumu</p>
            @if (! $user->isMember())
                <p class="mt-2 text-2xl font-semibold text-ink">Muaf</p>
                <p class="mt-2 text-xs text-ink-muted">Dernek üyesi olmayanlar aidat ödemez</p>
            @else
                <p class="mt-2 text-2xl font-semibold {{ $hasDuesDebt ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                    {{ $hasDuesDebt ? 'Borçlu' : 'Güncel' }}
                </p>
                <p class="mt-2 text-xs text-ink-muted">
                    {{ $hasDuesDebt ? '₺' . number_format($duesDebtTotal, 0, ',', '.') . ' ödenmedi' : 'Borcunuz bulunmuyor' }}
                </p>
            @endif
        </a>

        <a href="{{ route('customer.reservations.index') }}" class="stat-card surface-hover block">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Başvurularım</p>
            <p class="mt-2 text-2xl font-semibold text-ink">{{ $total }}</p>
            <p class="mt-2 text-xs text-ink-muted">
                {{ $pendingCount > 0 ? $pendingCount . ' tanesi inceleniyor' : 'İnceleme bekleyen yok' }}
            </p>
        </a>

        <div class="stat-card">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Bekleyen bakiye</p>
            <p class="mt-2 text-2xl font-semibold text-ink">₺{{ number_format($balanceTotal, 0, ',', '.') }}</p>
            <p class="mt-2 text-xs text-ink-muted">
                {{ $balanceTotal > 0 ? $awaitingPayment->count() . ' başvuru için ödeme bekleniyor' : 'Ödenecek bakiye yok' }}
            </p>
        </div>
    </div>

    {{-- Ödeme bekleyen başvurular --}}
    @foreach ($awaitingPayment as $reservation)
        <div class="surface mb-4 overflow-hidden border-accent-300 dark:border-accent-700">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="section-label">Yer tahsis edildi · ödeme bekleniyor</p>
                    <p class="mt-1 text-lg font-semibold text-ink">
                        {{ $reservation->facility->name }} · {{ $reservation->period->label() }}
                    </p>
                    <p class="mt-0.5 text-sm text-ink-muted">
                        Bakiye <x-money :value="$reservation->balanceDue()" class="font-semibold text-ink" />
                        @if ($reservation->balance_due_date)
                            · son ödeme {{ $reservation->balance_due_date->translatedFormat('d F Y') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-accent shrink-0">Bakiyeyi Öde</a>
            </div>
        </div>
    @endforeach

    {{-- Yaklaşan konaklama --}}
    @if ($upcoming)
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">Yaklaşan konaklamanız</h2>
            </div>
            <div class="grid gap-px bg-line sm:grid-cols-4">
                <div class="bg-surface px-5 py-4">
                    <p class="text-xs text-ink-muted">Tesis</p>
                    <p class="mt-1 font-medium text-ink">{{ $upcoming->facility->name }}</p>
                </div>
                <div class="bg-surface px-5 py-4">
                    <p class="text-xs text-ink-muted">Oda tipi</p>
                    <p class="mt-1 font-medium text-ink">{{ $upcoming->roomType->name }}</p>
                </div>
                <div class="bg-surface px-5 py-4">
                    <p class="text-xs text-ink-muted">Giriş</p>
                    <p class="mt-1 font-medium text-ink">{{ $upcoming->start_date->translatedFormat('d F Y') }}</p>
                    <p class="text-[11px] text-ink-muted">Pazar</p>
                </div>
                <div class="bg-surface px-5 py-4">
                    <p class="text-xs text-ink-muted">Çıkış</p>
                    <p class="mt-1 font-medium text-ink">{{ $upcoming->end_date->translatedFormat('d F Y') }}</p>
                    <p class="text-[11px] text-ink-muted">Cumartesi 08.00'a kadar</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3">
                <p class="text-xs text-ink-muted">
                    Devre başlangıcından önceki cumartesi 19.00'dan sonra giriş yapabilirsiniz; o gece için yatak ücreti alınmaz.
                </p>
                <a href="{{ route('customer.reservations.show', $upcoming) }}" class="btn-secondary !px-3 !py-1.5 text-xs shrink-0">Detay</a>
            </div>
        </div>
    @endif

    {{-- Son başvurular --}}
    <div class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="text-base font-semibold text-ink">Son başvurularım</h2>
            @if ($total > 0)
                <a href="{{ route('customer.reservations.index') }}" class="text-xs font-semibold text-accent-600 hover:text-accent-700 dark:text-accent-400">Tümü →</a>
            @endif
        </div>

        @if ($recent->isEmpty())
            <div class="empty-state">
                <svg class="h-10 w-10 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                <p class="font-medium text-ink-muted">Henüz bir başvurunuz yok.</p>
                @if ($canApply)
                    <a href="{{ route('customer.reservations.create') }}" class="btn-primary mt-2">İlk başvurunuzu oluşturun</a>
                @endif
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($recent as $reservation)
                    <li>
                        <a href="{{ route('customer.reservations.show', $reservation) }}"
                           class="flex items-center justify-between gap-4 px-5 py-3.5 transition-colors hover:bg-surface-alt">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-ink">{{ $reservation->facility->name }}</p>
                                    <x-status-badge :status="$reservation->status" />
                                </div>
                                <p class="mt-0.5 text-xs text-ink-muted">
                                    {{ $reservation->code }} · {{ $reservation->roomType->name }} ·
                                    {{ $reservation->start_date->translatedFormat('d M') }} – {{ $reservation->end_date->translatedFormat('d M Y') }}
                                </p>
                            </div>
                            <x-money :value="$reservation->total_price" class="shrink-0 font-semibold text-ink" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
