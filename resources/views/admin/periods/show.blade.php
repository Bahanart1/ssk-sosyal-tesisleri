<x-layouts.admin :title="$period->facility->name . ' · ' . $period->label()">

    @php
        // Yalnız yatak tahsis edilen kişiler sayılır (0-5 yaş yatak işgal etmez)
        $bedOccupants = $roster->where('age_category', '!=', 'child_0_5')->count();
    @endphp

    <div x-data="{ showClosed: false }" class="mx-auto max-w-6xl">

        <a href="{{ route('admin.periods.index', ['facility' => $period->facility_id, 'year' => $period->year]) }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Devreler
        </a>

        {{-- Başlık --}}
        <div class="mt-4 mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="section-label">{{ $period->facility->name }}</p>
                <h1 class="page-title mt-1">{{ $period->label() }}</h1>
                <p class="page-subtitle">
                    {{ $period->dateRange() }} · {{ $period->nights }} gün
                    @if ($period->combine_group) · birleşim grubu {{ $period->combine_group }} @endif
                </p>
                @if ($period->note)
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ $period->note }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($period->is_discounted)
                    <span class="badge-accent">İndirimli devre</span>
                @endif
                <span class="badge-{{ $period->is_open ? 'green' : 'gray' }}">{{ $period->is_open ? 'Başvuruya açık' : 'Başvuruya kapalı' }}</span>
                <form method="POST" action="{{ route('admin.periods.toggle', $period) }}">
                    @csrf
                    <button class="btn-secondary">{{ $period->is_open ? 'Başvuruya kapat' : 'Başvuruya aç' }}</button>
                </form>
            </div>
        </div>

        {{-- Özet --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-charts.stat label="Yer tahsis edilen" :value="$allocated->count()"
                           :hint="$capacity > 0 ? '%' . round(($allocated->count() / $capacity) * 100) . ' doluluk · ' . $capacity . ' ünite' : null" />
            <x-charts.stat label="İnceleme bekleyen" :value="$pending->count()"
                           hint="Karar verilmedi" />
            <x-charts.stat label="Konaklayacak kişi" :value="$roster->count()"
                           :hint="$bedOccupants . ' kişiye yatak tahsis edilir'" />
            <x-charts.stat label="Tahsil edilen"
                           value="₺{{ number_format($totals['collected'], 0, ',', '.') }}"
                           :hint="$totals['outstanding'] > 0 ? '₺' . number_format($totals['outstanding'], 0, ',', '.') . ' bakiye bekliyor' : 'Bakiye kalmadı'" />
        </div>

        {{-- Oda tipi dağılımı --}}
        <x-panel class="mb-6" title="Oda tipi dağılımı" subtitle="Bu devrede tahsis edilen ve bekleyen talepler">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Oda tipi</th>
                            <th>Yatak</th>
                            <th>Envanter</th>
                            <th>Tahsis edilen</th>
                            <th>Bekleyen</th>
                            <th>Kalan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($roomTypes as $row)
                            @php $remaining = $row['roomType']->quantity - $row['allocated']; @endphp
                            <tr>
                                <td class="font-medium">
                                    {{ $row['roomType']->name }}
                                    @if ($row['roomType']->kind === 'villa')
                                        <span class="badge-gray ml-1">Villa</span>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $row['roomType']->bed_count }}</td>
                                <td class="tabular-nums">{{ $row['roomType']->quantity }}</td>
                                <td class="tabular-nums font-semibold">{{ $row['allocated'] }}</td>
                                <td class="tabular-nums">
                                    @if ($row['pending'] > 0)
                                        <span class="badge-amber">{{ $row['pending'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="tabular-nums {{ $remaining <= 0 ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                                    {{ $remaining }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        {{-- Yer tahsis edilen başvurular --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-ink">Yer tahsis edilen üyeler ({{ $allocated->count() }})</h2>
                        <p class="text-xs text-ink-muted">Onaylanmış ve ödemesi tamamlanmış başvurular</p>
                    </div>
                    <a href="{{ route('admin.rooms.index', ['tesis' => $period->facility->slug, 'devre' => $period->id]) }}"
                       class="btn-secondary !px-3 !py-1.5 text-xs">Bu devrede boş odalar</a>
                </div>
                @php $odasiz = $allocated->whereNull('room_id')->count(); @endphp
                @if ($odasiz > 0)
                    <p class="mt-3 text-xs font-medium" style="color: var(--status-warn)">
                        {{ $odasiz }} başvuruya henüz fiziksel oda atanmadı.
                    </p>
                @endif
            </div>

            @if ($allocated->isEmpty())
                <div class="empty-state !py-10"><p class="text-sm text-ink-subtle">Bu devrede henüz yer tahsisi yapılmadı.</p></div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Üye</th>
                                <th>Grup</th>
                                <th>Oda tipi</th>
                                <th>Oda</th>
                                <th>Kişi</th>
                                <th>Tutar</th>
                                <th>Bakiye</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($allocated as $reservation)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.customers.show', $reservation->user) }}" class="font-medium text-ink hover:text-accent-600 dark:hover:text-accent-400">
                                            {{ $reservation->user->name }}
                                        </a>
                                        <p class="text-xs text-ink-muted">
                                            {{ $reservation->user->membership_no ?? $reservation->user->maskedTcNo() }}
                                            @if ($reservation->user->phone) · {{ $reservation->user->phone }} @endif
                                        </p>
                                    </td>
                                    <td class="text-xs">{{ $reservation->user->customerGroup?->name }}</td>
                                    <td class="text-xs">
                                        {{ $reservation->roomType->name }}
                                        @if ($reservation->isTwoPeriods())
                                            <p class="text-ink-muted">Birleşik devre · {{ $reservation->nights }} gün</p>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        @if ($reservation->room)
                                            <span class="font-medium text-ink">{{ $reservation->room->label() }}</span>
                                        @else
                                            <a href="{{ route('admin.reservations.edit', $reservation) }}"
                                               class="font-medium hover:underline" style="color: var(--status-warn)">Ata</a>
                                        @endif
                                    </td>
                                    <td class="tabular-nums">{{ $reservation->guests->count() }}</td>
                                    <td class="tabular-nums"><x-money :value="$reservation->total_price" class="font-semibold" /></td>
                                    <td class="tabular-nums">
                                        @if ($reservation->balanceDue() > 0)
                                            <x-money :value="$reservation->balanceDue()" class="font-semibold text-amber-700 dark:text-amber-300" />
                                        @else
                                            <span class="text-xs text-emerald-700 dark:text-emerald-400">Tamamlandı</span>
                                        @endif
                                    </td>
                                    <td><x-status-badge :status="$reservation->status" /></td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn-ghost !px-2.5 !py-1 text-xs">Aç</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- İnceleme bekleyen başvurular --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">İnceleme bekleyen başvurular ({{ $pending->count() }})</h2>
                <p class="text-xs text-ink-muted">Talep gönderilmiş, karar verilmemiş</p>
            </div>

            @if ($pending->isEmpty())
                <div class="empty-state !py-10"><p class="text-sm text-ink-subtle">Bekleyen başvuru yok.</p></div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Üye</th>
                                <th>Grup</th>
                                <th>Talep edilen oda</th>
                                <th>Kişi</th>
                                <th>Tutar</th>
                                <th>Peşinat</th>
                                <th>Aidat</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($pending as $reservation)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.customers.show', $reservation->user) }}" class="font-medium text-ink hover:text-accent-600 dark:hover:text-accent-400">
                                            {{ $reservation->user->name }}
                                        </a>
                                        <p class="text-xs text-ink-muted">
                                            {{ $reservation->code }} · {{ $reservation->created_at->format('d.m.Y') }} tarihli müracaat
                                        </p>
                                    </td>
                                    <td class="text-xs">{{ $reservation->user->customerGroup?->name }}</td>
                                    <td class="text-xs">
                                        {{ $reservation->roomType->name }}
                                        @if ($reservation->ground_floor_request)
                                            <p class="text-amber-700 dark:text-amber-300">Zemin kat talebi</p>
                                        @endif
                                    </td>
                                    <td class="tabular-nums">{{ $reservation->guests->count() }}</td>
                                    <td class="tabular-nums"><x-money :value="$reservation->total_price" class="font-semibold" /></td>
                                    <td><x-status-badge :status="$reservation->deposit_status" /></td>
                                    <td>
                                        @if (! $reservation->user->isMember())
                                            <span class="badge-gray">Muaf</span>
                                        @elseif ($reservation->user->hasDuesDebt())
                                            <span class="badge-red">Borçlu</span>
                                        @else
                                            <span class="badge-green">Güncel</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.reservations.edit', $reservation) }}" class="btn-primary !px-2.5 !py-1 text-xs">Değerlendir</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Konaklayacak kişiler --}}
        <x-panel class="mb-6"
                 title="Konaklayacak kişiler ({{ $roster->count() }})"
                 subtitle="Yer tahsis edilen başvurulardaki kişilerin tamamı — tesise giriş listesi">
            @if ($roster->isEmpty())
                <div class="empty-state !py-8"><p class="text-sm text-ink-subtle">Henüz kişi listesi oluşmadı.</p></div>
            @else
                <div class="-mx-5 -my-5 overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th>TC No</th>
                                <th>Doğum</th>
                                <th>Yaş grubu</th>
                                <th>Grup</th>
                                <th>Başvuru sahibi</th>
                                <th>Oda tipi</th>
                                <th>Kimlik</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($roster as $guest)
                                <tr>
                                    <td class="font-medium">{{ $guest->full_name }}</td>
                                    <td class="font-mono text-xs">{{ $guest->tc_no }}</td>
                                    <td class="text-xs">{{ $guest->birth_date->format('d.m.Y') }}</td>
                                    <td class="text-xs">
                                        {{ $guest->ageCategoryLabel() }}
                                        @if ($guest->wants_meal)
                                            <span class="badge-amber ml-1">yemekli</span>
                                        @endif
                                    </td>
                                    <td class="text-xs">{{ $guest->customerGroup->name }}</td>
                                    <td class="text-xs text-ink-muted">{{ $guest->reservation->user->name }}</td>
                                    <td class="text-xs">{{ $guest->reservation->roomType->name }}</td>
                                    <td>
                                        @if ($guest->id_document_path)
                                            <a href="{{ route('documents.identity', $guest) }}" target="_blank" rel="noopener"
                                               class="btn-ghost !px-2.5 !py-1 text-xs">Görüntüle</a>
                                        @else
                                            <span class="badge-red">Eksik</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>

        {{-- Reddedilen / iptal edilen --}}
        @if ($closed->isNotEmpty())
            <div class="surface overflow-hidden">
                <button type="button" @click="showClosed = ! showClosed"
                        class="flex w-full items-center justify-between px-5 py-4 text-left">
                    <span class="text-base font-semibold text-ink">Reddedilen ve iptal edilenler ({{ $closed->count() }})</span>
                    <svg class="h-5 w-5 text-ink-subtle transition-transform" :class="showClosed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>

                <div x-show="showClosed" x-cloak class="overflow-x-auto border-t border-line">
                    <table class="data-table">
                        <thead>
                            <tr><th>Üye</th><th>Oda tipi</th><th>Durum</th><th>Gerekçe</th><th></th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($closed as $reservation)
                                <tr>
                                    <td class="font-medium">{{ $reservation->user->name }}</td>
                                    <td class="text-xs">{{ $reservation->roomType->name }}</td>
                                    <td><x-status-badge :status="$reservation->status" /></td>
                                    <td class="max-w-md text-xs text-ink-muted">{{ $reservation->admin_note ?: '—' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn-ghost !px-2.5 !py-1 text-xs">Aç</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
