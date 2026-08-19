<x-layouts.customer title="Rezervasyonlarım">

    @php
        $tabs = [
            '' => 'Tümü',
            'pending' => 'Değerlendiriliyor',
            'approved' => 'Yeriniz ayrıldı',
            'paid' => 'Ödendi',
            'rejected' => 'Reddedildi',
            'cancelled' => 'İptal',
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-label">Tatil planınız</p>
            <h1 class="page-title mt-1">Rezervasyonlarım</h1>
            <p class="page-subtitle">Geçmiş ve güncel tüm rezervasyonlarınız.</p>
        </div>

        @if ($canApply)
            <a href="{{ route('customer.reservations.create') }}" class="btn-accent shrink-0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Rezervasyon yap
            </a>
        @endif
    </div>

    {{-- Durum süzgeci --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($tabs as $value => $label)
            @php $active = (string) request('status') === (string) $value; @endphp
            <a href="{{ route('customer.reservations.index', array_filter(['status' => $value ?: null])) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors
                      {{ $active ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
                {{ $label }}
                @if ($value && isset($counts[$value]))
                    <span class="rounded px-1.5 py-0.5 text-[10px] tabular-nums {{ $active ? 'bg-white/20' : 'bg-surface-sunken' }}">{{ $counts[$value] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="surface overflow-hidden">
        @if ($reservations->isEmpty())
            <div class="empty-state !py-16">
                <svg class="h-10 w-10 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                <p class="font-medium text-ink-muted">
                    {{ request('status') ? 'Bu durumda rezervasyonunuz yok.' : 'Henüz bir rezervasyonunuz yok.' }}
                </p>
                @if ($canApply && ! request('status'))
                    <a href="{{ route('customer.reservations.create') }}" class="btn-primary mt-2">İlk rezervasyonunuzu yapın</a>
                @endif
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($reservations as $reservation)
                    <li>
                        <a href="{{ route('customer.reservations.show', $reservation) }}"
                           class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-surface-alt sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-4">
                                <img src="{{ $reservation->facility->coverUrl() }}" alt=""
                                     class="h-16 w-24 shrink-0 rounded-xl object-cover" loading="lazy">
                                <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-ink">{{ $reservation->facility->name }}</p>
                                    <x-status-badge :status="$reservation->status" :label="$reservation->collectsOnSite() ? 'Tesiste Ödeyecek' : null" />
                                </div>
                                <p class="mt-1 text-xs text-ink-muted">
                                    {{ $reservation->code }} · {{ $reservation->roomType->name }} ·
                                    {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->number }}.@endif
                                </p>
                                <p class="text-xs text-ink-muted">
                                    {{ $reservation->start_date->translatedFormat('d F') }} – {{ $reservation->end_date->translatedFormat('d F Y') }}
                                    ({{ $reservation->nights }} gün)
                                </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center justify-between gap-4 sm:justify-end">
                                <div class="text-right">
                                    <x-money :value="$reservation->total_price" class="block font-semibold text-ink" />
                                    @if ($reservation->collectsOnSite() && $reservation->balanceDue() > 0)
                                        <span class="text-[11px] font-medium text-teal-700 dark:text-teal-300">
                                            <x-money :value="$reservation->balanceDue()" /> tesiste
                                        </span>
                                    @elseif ($reservation->status === 'approved' && $reservation->balanceDue() > 0)
                                        <span class="text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                            <x-money :value="$reservation->balanceDue()" /> bakiye ödenecek
                                        </span>
                                    @elseif ($reservation->status === 'paid')
                                        <span class="block text-[11px] text-emerald-700 dark:text-emerald-400">Ödeme tamamlandı</span>
                                    @endif

                                    {{-- Bekleyen ya da yapılmış iade, tutarın altında görünür --}}
                                    @if ($reservation->refund)
                                        @if ($reservation->refund->isPaid())
                                            <span class="block text-[11px] text-ink-muted">
                                                <x-money :value="$reservation->refund->amount" /> iade edildi
                                            </span>
                                        @elseif ($reservation->refund->status !== 'cancelled')
                                            <span class="block text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                                <x-money :value="$reservation->refund->amount" /> iade edilecek
                                            </span>
                                        @endif
                                    @endif
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
