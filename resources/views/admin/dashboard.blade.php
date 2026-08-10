<x-layouts.admin title="Genel Bakış">

    <div class="mb-8">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Genel bakış</h1>
        <p class="page-subtitle">Rezervasyon sisteminin güncel durumu ve son hareketler.</p>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-5">
        @php
            $cards = [
                ['label' => 'Toplam rezervasyon', 'value' => $stats['total'], 'accent' => '#0f2038'],
                ['label' => 'Onay bekleyen', 'value' => $stats['pending'], 'accent' => '#d97706'],
                ['label' => 'Onaylanan', 'value' => $stats['approved'], 'accent' => '#1a9e92'],
                ['label' => 'Ödenen', 'value' => $stats['paid'], 'accent' => '#059669'],
                ['label' => 'Toplam müşteri', 'value' => $stats['customers'], 'accent' => '#2f5480'],
            ];
        @endphp
        @foreach ($cards as $i => $c)
            <div class="stat-card surface-hover animate-rise" style="--stat-accent: {{ $c['accent'] }}; animation-delay: {{ $i * 60 }}ms">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $c['label'] }}</p>
                <p class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900">{{ $c['value'] }}</p>
            </div>
        @endforeach
    </div>

    <section class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100/80 px-6 py-4">
            <h2 class="font-display text-lg font-semibold text-navy-900">Son rezervasyonlar</h2>
            <a href="{{ route('admin.reservations.index') }}" class="text-sm font-semibold text-teal-700 transition hover:text-teal-800">Tümünü gör →</a>
        </div>

        @if ($recent->isEmpty())
            <div class="empty-state">
                <p class="text-sm text-slate-400">Henüz rezervasyon bulunmuyor.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>Tesis</th>
                            <th>Tarih</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recent as $r)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.reservations.show', $r) }}" class="block hover:text-teal-800">
                                        <p class="font-semibold">{{ $r->user->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $r->user->maskedTcNo() }}</p>
                                    </a>
                                </td>
                                <td>{{ $r->facility->name }}</td>
                                <td>{{ $r->check_in->format('d.m.Y') }}</td>
                                <td class="font-medium">₺{{ number_format($r->total_price, 0, ',', '.') }}</td>
                                <td><x-status-badge :status="$r->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
