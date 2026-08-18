<x-layouts.admin title="Başvurular">

    <div class="mb-6">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Başvurular</h1>
        <p class="page-subtitle">Müracaatları inceleyin, düzenleyin ve yer tahsisi yapın.</p>
    </div>

    {{-- İş akışı sekmeleri: her başvuru tam olarak bir aşamada durur --}}
    <div class="surface mb-5 overflow-hidden">
        {{-- gap-px + arka plan, sarma sonrası da düzgün ayraç verir --}}
        <div class="grid grid-cols-2 gap-px bg-line sm:grid-cols-4 xl:grid-cols-7">
            @php
                $tumu = array_sum($stageCounts);
                $aktifYok = $stage === null;
            @endphp

            <a href="{{ route('admin.reservations.index', request()->except(['stage', 'page'])) }}"
               class="flex flex-col gap-0.5 px-4 py-3 transition-colors {{ $aktifYok ? 'bg-accent-50 dark:bg-accent-900/25' : 'bg-surface hover:bg-surface-alt' }}">
                <span class="text-lg font-semibold tabular-nums text-ink">{{ $tumu }}</span>
                <span class="text-[11px] font-medium {{ $aktifYok ? 'text-accent-700 dark:text-accent-300' : 'text-ink-muted' }}">Tümü</span>
            </a>

            @foreach ($stages as $key => $tanim)
                @php $aktif = $stage === $key; @endphp
                <a href="{{ route('admin.reservations.index', ['stage' => $key] + request()->except(['stage', 'status', 'page'])) }}"
                   class="flex flex-col gap-0.5 px-4 py-3 transition-colors {{ $aktif ? 'bg-accent-50 dark:bg-accent-900/25' : 'bg-surface hover:bg-surface-alt' }}"
                   title="{{ $tanim['hint'] }}">
                    <span class="text-lg font-semibold tabular-nums {{ $stageCounts[$key] > 0 && ! in_array($key, ['done', 'closed', 'balance'], true) ? 'text-ink' : 'text-ink-muted' }}">
                        {{ $stageCounts[$key] }}
                    </span>
                    <span class="text-[11px] font-medium leading-tight {{ $aktif ? 'text-accent-700 dark:text-accent-300' : 'text-ink-muted' }}">
                        {{ $tanim['label'] }}
                    </span>
                </a>
            @endforeach

            {{-- 7 sekme 2 ve 4 sütuna tam bölünmediği için son gözü doldurur --}}
            <div class="bg-surface xl:hidden"></div>
        </div>

        @if ($stage)
            <p class="border-t border-line bg-surface-alt px-4 py-2.5 text-xs text-ink-muted">
                {{ $stages[$stage]['hint'] }}
            </p>
        @endif
    </div>

    {{-- Filtreler --}}
    @php
        $filtreVar = request()->hasAny(['q', 'facility', 'period', 'room', 'deposit']);
    @endphp

    <form method="GET" class="surface mb-6 p-4">
        <input type="hidden" name="stage" value="{{ $stage }}">

        <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2 xl:col-span-1">
                <label for="f-q" class="field-label">Ara</label>
                <input id="f-q" type="text" name="q" value="{{ request('q') }}"
                       placeholder="Başvuru no, ad, TC veya üyelik no" class="field-input">
            </div>

            <div>
                <label for="f-facility" class="field-label">Tesis</label>
                <select id="f-facility" name="facility" class="field-input">
                    <option value="">Tüm tesisler</option>
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected(request('facility') == $facility->id)>{{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="f-period" class="field-label">Devre</label>
                <select id="f-period" name="period" class="field-input"
                        title="Birleşik devre başvuruları her iki devrede de listelenir.">
                    <option value="">Tüm devreler</option>
                    @foreach ($periodsByFacility as $facilityName => $periods)
                        <optgroup label="{{ $facilityName }}">
                            @foreach ($periods as $period)
                                <option value="{{ $period->id }}" @selected(request('period') == $period->id)>
                                    {{ $periodLabel($period) }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="f-deposit" class="field-label">Peşinat</label>
                <select id="f-deposit" name="deposit" class="field-input">
                    <option value="">Tüm peşinat durumları</option>
                    <option value="pending" @selected(request('deposit') === 'pending')>Bekliyor</option>
                    <option value="verified" @selected(request('deposit') === 'verified')>Doğrulandı</option>
                    <option value="rejected" @selected(request('deposit') === 'rejected')>Reddedildi</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
            <p class="text-xs text-ink-muted">
                <strong class="tabular-nums text-ink">{{ $reservations->total() }}</strong>
                {{ $filtreVar || $stage ? 'başvuru eşleşti' : 'başvuru' }}

                @if (request('period'))
                    <span class="text-ink-subtle">· Birleşik devre başvuruları her iki devrede de listelenir.</span>
                @endif
            </p>

            <div class="flex items-center gap-2">
                @if ($filtreVar)
                    <a href="{{ route('admin.reservations.index', array_filter(['stage' => $stage])) }}" class="btn-ghost !px-3 !py-1.5 text-xs">Temizle</a>
                @endif
                <button type="submit" class="btn-primary !px-4 !py-1.5 text-xs">Filtrele</button>
            </div>
        </div>
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
                            <th>Oda</th>
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
                                <td>
                                    @include('admin.reservations._room-assign', [
                                        'reservation' => $reservation,
                                        'options' => $roomOptions[$reservation->id] ?? null,
                                    ])
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
                        <div class="mt-3">
                            @include('admin.reservations._room-assign', [
                                'reservation' => $reservation,
                                'options' => $roomOptions[$reservation->id] ?? null,
                            ])
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
