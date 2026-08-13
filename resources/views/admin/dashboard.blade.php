<x-layouts.admin title="Genel Bakış">

    <div class="mb-8">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Genel bakış</h1>
        <p class="page-subtitle">Başvuru, tahsilat ve devre doluluk durumu.</p>
    </div>

    {{-- İstatistikler --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['label' => 'İnceleme bekleyen', 'value' => $stats['pending'], 'accent' => '#e8734a', 'href' => route('admin.reservations.index', ['status' => 'pending'])],
                ['label' => 'Yer tahsis edilen', 'value' => $stats['approved'], 'accent' => '#0f766e', 'href' => route('admin.reservations.index', ['status' => 'approved'])],
                ['label' => 'Ödemesi tamamlanan', 'value' => $stats['paid'], 'accent' => '#059669', 'href' => route('admin.reservations.index', ['status' => 'paid'])],
                ['label' => 'Kayıtlı üye', 'value' => $stats['customers'], 'accent' => '#0a1728', 'href' => route('admin.customers.index')],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ $card['href'] }}" class="stat-card surface-hover" style="--stat-accent: {{ $card['accent'] }}">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-500">{{ $card['label'] }}</p>
                <p class="mt-2 font-display text-3xl font-semibold text-navy-900">{{ $card['value'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mb-8 grid gap-4 lg:grid-cols-2">
        <div class="stat-card" style="--stat-accent: #059669">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-500">Toplam tahsilat</p>
            <x-money :value="$stats['collected']" class="mt-2 block font-display text-3xl font-semibold text-navy-900" />
        </div>
        <a href="{{ route('admin.payments.index', ['status' => 'pending', 'method' => 'bank_transfer']) }}"
           class="stat-card surface-hover" style="--stat-accent: #d97706">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-500">Doğrulama bekleyen dekont</p>
            <p class="mt-2 font-display text-3xl font-semibold text-navy-900">{{ $stats['awaiting_receipts'] }}</p>
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Son başvurular --}}
        <div class="surface overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Son başvurular</h2>
                <a href="{{ route('admin.reservations.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-800">Tümü →</a>
            </div>

            @if ($recent->isEmpty())
                <p class="px-6 py-12 text-center text-sm text-stone-400">Henüz başvuru yok.</p>
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($recent as $reservation)
                        <li>
                            <a href="{{ route('admin.reservations.show', $reservation) }}"
                               class="flex items-center justify-between gap-4 px-6 py-3.5 transition-colors hover:bg-teal-50/30">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-navy-900">{{ $reservation->user->name }}</p>
                                    <p class="truncate text-xs text-stone-500">
                                        {{ $reservation->code }} · {{ $reservation->facility->name }} ·
                                        {{ $reservation->period->label() }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-money :value="$reservation->total_price" class="hidden text-sm font-semibold text-navy-800 sm:block" />
                                    <x-status-badge :status="$reservation->status" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Doğrulama bekleyen dekontlar --}}
        <div class="surface overflow-hidden">
            <div class="border-b border-stone-100/80 px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-navy-900">Bekleyen dekontlar</h2>
            </div>

            @if ($pendingReceipts->isEmpty())
                <p class="px-6 py-12 text-center text-sm text-stone-400">Bekleyen dekont yok.</p>
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($pendingReceipts as $payment)
                        <li class="px-6 py-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-navy-900">{{ $payment->reservation->user->name }}</p>
                                    <p class="text-xs text-stone-500">{{ $payment->kindLabel() }} · {{ $payment->reservation->code }}</p>
                                </div>
                                <x-money :value="$payment->amount" class="shrink-0 text-sm font-semibold text-navy-800" />
                            </div>
                            <div class="mt-2 flex gap-2">
                                @if ($payment->receipt_path)
                                    <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener"
                                       class="btn-ghost !px-2.5 !py-1 text-xs">Dekont</a>
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
        </div>
    </div>

    {{-- Devre doluluk --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        @foreach ($occupancy as $row)
            <div class="surface overflow-hidden">
                <div class="border-b border-stone-100/80 px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-navy-900">{{ $row['facility']->name }}</h2>
                    <p class="text-xs text-stone-500">Yaklaşan devreler · başvuru sayısı</p>
                </div>

                @if ($row['periods']->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-stone-400">Yaklaşan açık devre yok.</p>
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($row['periods'] as $item)
                            <li class="flex items-center justify-between px-6 py-3 text-sm">
                                <div>
                                    <span class="font-medium text-navy-900">{{ $item['period']->label() }}</span>
                                    @if ($item['period']->is_discounted)
                                        <span class="badge-teal ml-2 !py-0.5 !text-[10px]">İndirimli</span>
                                    @endif
                                    <p class="text-xs text-stone-500">{{ $item['period']->dateRange() }}</p>
                                </div>
                                <span class="rounded-lg bg-sand-100 px-2.5 py-1 text-xs font-semibold text-navy-700">
                                    {{ $item['reservations'] }} başvuru
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.admin>
