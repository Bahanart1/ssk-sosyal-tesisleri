<x-layouts.admin title="Başvurular">

    <div class="mb-6">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Başvurular</h1>
        <p class="page-subtitle">Müracaatları inceleyin, düzenleyin ve yer tahsisi yapın.</p>
    </div>

    {{-- Durum sekmeleri --}}
    @php
        $tabs = [
            '' => 'Tümü',
            'pending' => 'İnceleniyor',
            'approved' => 'Yer tahsis edildi',
            'paid' => 'Ödendi',
            'rejected' => 'Reddedildi',
            'cancelled' => 'İptal',
        ];
    @endphp

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($tabs as $value => $label)
            @php $active = (string) request('status') === (string) $value; @endphp
            <a href="{{ route('admin.reservations.index', array_filter(['status' => $value ?: null] + request()->except(['status', 'page']))) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {{ $active ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
                {{ $label }}
                @if ($value && isset($counts[$value]))
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] {{ $active ? 'bg-white/15' : 'bg-surface-sunken' }}">{{ $counts[$value] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Filtreler --}}
    <form method="GET" class="surface mb-6 flex flex-wrap items-end gap-3 p-4">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="min-w-[14rem] flex-1">
            <label class="field-label">Ara</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Başvuru no, ad, TC veya üyelik no" class="field-input">
        </div>
        <div>
            <label class="field-label">Tesis</label>
            <select name="facility" class="field-input">
                <option value="">Tümü</option>
                @foreach ($facilities as $facility)
                    <option value="{{ $facility->id }}" @selected(request('facility') == $facility->id)>{{ $facility->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label">Peşinat</label>
            <select name="deposit" class="field-input">
                <option value="">Tümü</option>
                <option value="pending" @selected(request('deposit') === 'pending')>Bekliyor</option>
                <option value="verified" @selected(request('deposit') === 'verified')>Doğrulandı</option>
                <option value="rejected" @selected(request('deposit') === 'rejected')>Reddedildi</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Filtrele</button>
        @if (request()->hasAny(['q', 'facility', 'deposit']))
            <a href="{{ route('admin.reservations.index', ['status' => request('status')]) }}" class="btn-ghost">Temizle</a>
        @endif
    </form>

    <div class="surface overflow-hidden">
        @if ($reservations->isEmpty())
            <p class="px-6 py-16 text-center text-sm text-ink-subtle">Bu filtreye uyan başvuru bulunamadı.</p>
        @else
            {{-- Masaüstü --}}
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Başvuru</th>
                            <th>Üye</th>
                            <th>Tesis / Oda</th>
                            <th>Devre</th>
                            <th>Kişi</th>
                            <th>Tutar</th>
                            <th>Peşinat</th>
                            <th>Durum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($reservations as $reservation)
                            <tr>
                                <td class="font-mono text-xs">{{ $reservation->code }}</td>
                                <td>
                                    <p class="font-medium">{{ $reservation->user->name }}</p>
                                    <p class="text-xs text-ink-muted">{{ $reservation->user->membership_no ?? $reservation->user->maskedTcNo() }}</p>
                                </td>
                                <td>
                                    <p>{{ $reservation->facility->name }}</p>
                                    <p class="text-xs text-ink-muted">{{ $reservation->roomType->name }}</p>
                                </td>
                                <td class="text-xs">
                                    {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->number }}. @endif
                                    <p class="text-ink-muted">{{ $reservation->start_date->format('d.m.Y') }}</p>
                                </td>
                                <td>{{ $reservation->guests_count }}</td>
                                <td><x-money :value="$reservation->total_price" class="font-semibold" /></td>
                                <td><x-status-badge :status="$reservation->deposit_status" /></td>
                                <td><x-status-badge :status="$reservation->status" /></td>
                                <td class="text-right">
                                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn-ghost !px-3 !py-1.5 text-xs">Aç</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobil --}}
            <ul class="divide-y divide-line lg:hidden">
                @foreach ($reservations as $reservation)
                    <li class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $reservation->user->name }}</p>
                                <p class="text-xs text-ink-muted">
                                    {{ $reservation->code }} · {{ $reservation->facility->name }}<br>
                                    {{ $reservation->roomType->name }} · {{ $reservation->period->label() }}
                                </p>
                            </div>
                            <x-status-badge :status="$reservation->status" />
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <x-money :value="$reservation->total_price" class="font-semibold text-ink" />
                            <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn-secondary !px-3 !py-1.5 text-xs">Aç</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">{{ $reservations->links() }}</div>
</x-layouts.admin>
