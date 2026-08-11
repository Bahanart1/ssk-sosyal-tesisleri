<x-layouts.admin title="Rezervasyonlar">

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">Rezervasyonlar</h1>
            <p class="page-subtitle">Tüm rezervasyon taleplerini görüntüleyin ve yönetin.</p>
        </div>
    </div>

    <form method="GET" class="surface mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Müşteri adı veya TC No ile ara…" class="field-input sm:max-w-xs">
        <select name="status" class="field-input sm:max-w-[180px]" onchange="this.form.submit()">
            <option value="">Tüm durumlar</option>
            @foreach (['pending' => 'Onay Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'paid' => 'Ödendi', 'cancelled' => 'İptal Edildi'] as $val => $label)
                <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-secondary">Filtrele</button>
        @if (request('q') || request('status'))
            <a href="{{ route('admin.reservations.index') }}" class="text-sm font-medium text-stone-500 hover:text-navy-800">Temizle</a>
        @endif
    </form>

    <div class="surface overflow-hidden">
        @if ($reservations->isEmpty())
            <div class="empty-state">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-sand-100 text-stone-400">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                </div>
                <p class="font-medium text-stone-500">Kriterlere uygun rezervasyon bulunamadı.</p>
            </div>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>TC No</th>
                            <th>Sınıf</th>
                            <th>Tesis</th>
                            <th>Tarih</th>
                            <th>Süre</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($reservations as $r)
                            <tr>
                                <td class="font-semibold">{{ $r->user->name }}</td>
                                <td class="text-stone-500">{{ $r->user->maskedTcNo() }}</td>
                                <td>{{ $r->customerClass->name }}</td>
                                <td>{{ $r->facility->name }}</td>
                                <td>{{ $r->check_in->format('d.m.Y') }} - {{ $r->check_out->format('d.m.Y') }}</td>
                                <td>{{ $r->nights() }} gece</td>
                                <td class="font-medium">₺{{ number_format($r->total_price, 0, ',', '.') }}</td>
                                <td><x-status-badge :status="$r->status" /></td>
                                <td><a href="{{ route('admin.reservations.show', $r) }}" class="btn-ghost !px-3 !py-1.5 text-xs">İncele</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-stone-100 lg:hidden">
                @foreach ($reservations as $r)
                    <li class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-navy-900">{{ $r->user->name }}</p>
                                <p class="text-xs text-stone-500">{{ $r->user->maskedTcNo() }} · {{ $r->customerClass->name }}</p>
                            </div>
                            <x-status-badge :status="$r->status" />
                        </div>
                        <p class="mt-2 text-sm text-stone-600">{{ $r->facility->name }}</p>
                        <p class="text-xs text-stone-400">{{ $r->check_in->format('d.m.Y') }} - {{ $r->check_out->format('d.m.Y') }} · ₺{{ number_format($r->total_price, 0, ',', '.') }}</p>
                        <a href="{{ route('admin.reservations.show', $r) }}" class="btn-secondary mt-3 w-full">İncele</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">{{ $reservations->links() }}</div>
</x-layouts.admin>
