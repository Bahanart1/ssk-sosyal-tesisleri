<x-layouts.admin title="Genel Bakış">

    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">Genel bakış</h1>
            <p class="page-subtitle">{{ now()->translatedFormat('d F Y, l') }}</p>
        </div>

        @if ($counts['pending'] > 0 || $counts['receipts'] > 0)
            <div class="flex flex-wrap gap-2">
                @if ($counts['pending'] > 0)
                    <a href="{{ route('admin.reservations.index', ['status' => 'pending']) }}" class="btn-primary">
                        {{ $counts['pending'] }} başvuru inceleme bekliyor
                    </a>
                @endif
                @if ($counts['receipts'] > 0)
                    <a href="{{ route('admin.payments.index', ['status' => 'pending', 'method' => 'bank_transfer']) }}" class="btn-secondary">
                        {{ $counts['receipts'] }} dekont doğrulanacak
                    </a>
                @endif
            </div>
        @endif
    </div>

    {{-- Öne çıkan sayı + destekleyici göstergeler --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-4">
        <x-charts.stat
            class="lg:col-span-2"
            label="Toplam tahsilat"
            value="₺{{ number_format($hero['total'], 0, ',', '.') }}"
            :delta="$hero['delta']"
            :spark="$hero['spark']"
            :hero="true"
            :hint="$hero['delta'] ? null : 'Son 30 günde ₺' . number_format($hero['last30'], 0, ',', '.')"
        />

        <x-charts.stat
            label="İnceleme bekleyen"
            :value="$counts['pending']"
            :hint="$counts['total'] . ' başvurunun içinde'"
            :href="route('admin.reservations.index', ['status' => 'pending'])"
        />

        <x-charts.stat
            label="Yer tahsis edilen"
            :value="$counts['approved']"
            :hint="$counts['paid'] . ' başvurunun ödemesi tamamlandı'"
            :href="route('admin.reservations.index', ['status' => 'approved'])"
        />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-charts.stat label="Kayıtlı üye" :value="$counts['customers']"
                       :hint="$counts['duesDebt'] . ' üyenin aidat borcu var'"
                       :href="route('admin.customers.index')" />
        <x-charts.stat label="Bekleyen dekont" :value="$counts['receipts']"
                       hint="Havale doğrulaması gerekiyor"
                       :href="route('admin.payments.index', ['status' => 'pending', 'method' => 'bank_transfer'])" />
        <x-charts.stat label="Reddedilen" :value="$counts['rejected']"
                       :href="route('admin.reservations.index', ['status' => 'rejected'])" />
        <x-charts.stat label="İptal edilen" :value="$counts['cancelled']"
                       :href="route('admin.reservations.index', ['status' => 'cancelled'])" />
    </div>

    {{-- Tahsilat seyri + başvuru durumu --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <x-panel class="lg:col-span-2"
                 title="Tahsilat seyri"
                 subtitle="Son altı ayda doğrulanmış peşinat ve bakiye tahsilatı">
            <x-charts.area :points="$revenue" label="Aylık tahsilat" :height="210" />

            <x-slot:table>
                <table class="data-table">
                    <thead><tr><th>Ay</th><th class="text-right">Tahsilat</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($revenue as $point)
                            <tr>
                                <td>{{ $point['label'] }}</td>
                                <td class="text-right tabular-nums">{{ $point['display'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-slot:table>
        </x-panel>

        <x-panel title="Başvuru durumu" subtitle="Toplam {{ $counts['total'] }} başvurunun dağılımı">
            @if ($counts['total'] === 0)
                <div class="empty-state !py-10">
                    <p class="text-sm text-ink-subtle">Henüz başvuru yok.</p>
                </div>
            @else
                <x-charts.meter :rows="$statusMix" />
            @endif
        </x-panel>
    </div>

    {{-- Devre doluluğu: tesisler ayrı ayrı (küçük çoklu) --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        @foreach ($occupancy as $row)
            <x-panel :title="$row['facility']->name . ' · devre doluluğu'"
                     :subtitle="'Yaklaşan açık devreler · ' . $row['capacity'] . ' ünite kapasite'">
                <x-charts.columns :columns="$row['columns']" />

                <x-slot:table>
                    <table class="data-table">
                        <thead><tr><th>Devre</th><th>Tarih</th><th class="text-right">Başvuru</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($row['columns'] as $column)
                                <tr>
                                    <td>{{ $column['label'] }}. Devre</td>
                                    <td class="text-xs text-ink-muted">{{ $column['meta'] }}</td>
                                    <td class="text-right tabular-nums">{{ $column['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:table>
            </x-panel>
        @endforeach
    </div>

    {{-- Grup ve oda tipi dağılımı --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <x-panel title="Müşteri grubu dağılımı"
                 subtitle="Yer tahsis sürecindeki başvurularda konaklayan kişiler">
            {{-- Gruplar sıralı bir ölçek: renk açıktan koyuya sırayı taşır --}}
            <x-charts.bars :rows="$groupMix" :ramp="['var(--chart-ramp-1)', 'var(--chart-ramp-2)', 'var(--chart-ramp-3)']" />

            <x-slot:table>
                <table class="data-table">
                    <thead><tr><th>Grup</th><th>Kapsam</th><th class="text-right">Kişi</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($groupMix as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="max-w-md text-xs text-ink-muted">{{ $row['meta'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-slot:table>
        </x-panel>

        <x-panel title="Oda tipi tercihleri" subtitle="Başvurularda seçilen konaklama tipleri">
            <x-charts.bars :rows="$roomMix" />

            <x-slot:table>
                <table class="data-table">
                    <thead><tr><th>Oda tipi</th><th>Tesis</th><th class="text-right">Başvuru</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($roomMix as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-xs text-ink-muted">{{ $row['meta'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-slot:table>
        </x-panel>
    </div>

    {{-- İş listeleri --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <x-panel class="lg:col-span-2" title="Son başvurular" :subtitle="null">
            <x-slot:action>
                <a href="{{ route('admin.reservations.index') }}" class="text-xs font-semibold text-accent-700 dark:text-accent-300 hover:text-accent-800 dark:hover:text-accent-200">Tümü →</a>
            </x-slot:action>

            @if ($recent->isEmpty())
                <div class="empty-state !py-10"><p class="text-sm text-ink-subtle">Henüz başvuru yok.</p></div>
            @else
                <ul class="-mx-5 -my-5 divide-y divide-line">
                    @foreach ($recent as $reservation)
                        <li>
                            <a href="{{ route('admin.reservations.show', $reservation) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3.5 transition-colors hover:bg-accent-50/60 dark:hover:bg-accent-900/20">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $reservation->user->name }}</p>
                                    <p class="truncate text-xs text-ink-muted">
                                        {{ $reservation->code }} · {{ $reservation->facility->name }} ·
                                        {{ $reservation->roomType->name }} · {{ $reservation->period->label() }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-money :value="$reservation->total_price" class="hidden text-sm font-semibold tabular-nums text-ink sm:block" />
                                    <x-status-badge :status="$reservation->status" :label="$reservation->collectsOnSite() ? 'Tesiste Ödeyecek' : null" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>

        <x-panel title="Bekleyen dekontlar" subtitle="Havale bildirimlerinin doğrulanması">
            @if ($pendingReceipts->isEmpty())
                <div class="empty-state !py-10"><p class="text-sm text-ink-subtle">Bekleyen dekont yok.</p></div>
            @else
                <ul class="-mx-5 -my-5 divide-y divide-line">
                    @foreach ($pendingReceipts as $payment)
                        <li class="px-5 py-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $payment->reservation->user->name }}</p>
                                    <p class="text-xs text-ink-muted">{{ $payment->kindLabel() }} · {{ $payment->reservation->code }}</p>
                                </div>
                                <x-money :value="$payment->amount" class="shrink-0 text-sm font-semibold tabular-nums text-ink" />
                            </div>
                            <div class="mt-2.5 flex gap-2">
                                @if ($payment->receipt_path)
                                    <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener"
                                       class="btn-secondary !px-2.5 !py-1 text-xs">Dekont</a>
                                @endif
                                <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                                    @csrf
                                    <button class="btn-accent !px-2.5 !py-1 text-xs">Doğrula</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    </div>
</x-layouts.admin>
