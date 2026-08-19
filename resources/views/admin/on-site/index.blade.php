<x-layouts.admin title="Tesiste Tahsilat">

    <div class="mb-6">
        <p class="section-label">Günlük iş</p>
        <h1 class="page-title mt-1">Tesiste tahsilat</h1>
        <p class="page-subtitle">
            Bakiyesini tesise girişte ödeyecek üyeler. Rezervasyonları kesinleşti; para girişte alınır
            ve buradan işlenir.
        </p>
    </div>

    {{-- Toplamlar --}}
    <div class="surface mb-5 overflow-hidden">
        <div class="grid grid-cols-1 gap-px bg-line sm:grid-cols-3">
            <div class="bg-surface px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">
                    {{ $collected ? 'Tahsil edilen toplam' : 'Tesiste alınacak toplam' }}
                </p>
                <p class="mt-2 font-display text-2xl font-semibold tabular-nums text-ink">
                    <x-money :value="$total" />
                </p>
                <p class="mt-1 text-xs text-ink-muted">{{ $reservations->count() }} rezervasyon</p>
            </div>

            @foreach ($byFacility as $ad => $ozet)
                <div class="bg-surface px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">{{ $ad }}</p>
                    <p class="mt-2 font-display text-2xl font-semibold tabular-nums text-ink">
                        <x-money :value="$ozet['tutar']" />
                    </p>
                    <p class="mt-1 text-xs text-ink-muted">{{ $ozet['adet'] }} rezervasyon</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Sekmeler --}}
    <div class="mb-5 flex flex-wrap gap-2">
        <a href="{{ route('admin.on-site.index', request()->except(['durum'])) }}"
           class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {{ ! $collected ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
            Tahsil edilecek
            <span class="rounded-md px-1.5 py-0.5 text-[10px] {{ ! $collected ? 'bg-white/15' : 'bg-surface-sunken' }}">{{ $pendingCount }}</span>
        </a>
        <a href="{{ route('admin.on-site.index', ['durum' => 'collected'] + request()->except(['durum'])) }}"
           class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {{ $collected ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
            Tahsil edilenler
        </a>
    </div>

    {{-- Süzgeçler --}}
    <form method="GET" class="surface mb-6 p-4">
        <input type="hidden" name="durum" value="{{ request('durum') }}">
        <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="field-label">Ara</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Rezervasyon no veya üye adı" class="field-input">
            </div>
            <div>
                <label class="field-label">Tesis</label>
                <select name="tesis" class="field-input">
                    <option value="">Tüm tesisler</option>
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected(request('tesis') == $facility->id)>{{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Devre</label>
                <select name="devre" class="field-input">
                    <option value="">Tüm devreler</option>
                    @foreach ($periodsByFacility as $tesisAdi => $periods)
                        <optgroup label="{{ $tesisAdi }}">
                            @foreach ($periods as $period)
                                <option value="{{ $period->id }}" @selected(request('devre') == $period->id)>
                                    {{ $period->label() }} · {{ $period->dateRange() }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3">
            <p class="text-xs text-ink-muted">Giriş tarihine göre sıralı.</p>
            <div class="flex gap-2">
                @if (request()->hasAny(['q', 'tesis', 'devre']))
                    <a href="{{ route('admin.on-site.index', array_filter(['durum' => request('durum')])) }}" class="btn-ghost !px-3 !py-1.5 text-xs">Temizle</a>
                @endif
                <button type="submit" class="btn-primary !px-4 !py-1.5 text-xs">Filtrele</button>
            </div>
        </div>
    </form>

    <div class="surface overflow-hidden">
        @if ($reservations->isEmpty())
            <p class="px-6 py-16 text-center text-sm text-ink-subtle">
                {{ $collected ? 'Tahsil edilmiş kayıt yok.' : 'Tesiste tahsil edilecek bakiye yok.' }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Giriş</th>
                            <th>Üye</th>
                            <th>Tesis / Devre</th>
                            <th>Oda</th>
                            <th class="text-right">Alınacak</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($reservations as $reservation)
                            <tr>
                                <td class="text-xs">
                                    <p class="font-medium text-ink">{{ $reservation->start_date->translatedFormat('d M Y') }}</p>
                                    <p class="text-ink-muted">{{ $reservation->code }}</p>
                                </td>
                                <td>
                                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-medium text-ink hover:text-accent-600 dark:hover:text-accent-400">
                                        {{ $reservation->user->name }}
                                    </a>
                                    <p class="text-xs text-ink-muted">
                                        {{ $reservation->user->membership_no ?? $reservation->user->maskedTcNo() }}
                                        @if ($reservation->user->phone) · {{ $reservation->user->phone }} @endif
                                    </p>
                                </td>
                                <td class="text-xs">
                                    <p>{{ $reservation->facility->name }}</p>
                                    <p class="text-ink-muted">
                                        {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->number }}.@endif
                                        · {{ $reservation->roomType->name }}
                                    </p>
                                </td>
                                <td class="text-xs">
                                    @if ($reservation->room)
                                        <span class="font-medium text-ink">
                                            {{ $reservation->room->label() }}@if ($reservation->secondRoom) + {{ $reservation->secondRoom->label() }}@endif
                                        </span>
                                    @else
                                        <span style="color: var(--status-warn)">Atanmadı</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-money :value="$collected ? $reservation->onSiteCollected() : $reservation->balanceDue()"
                                             class="font-display text-base font-semibold tabular-nums" />
                                </td>
                                <td class="text-right">
                                    @if ($collected)
                                        <span class="badge-green">Tahsil edildi</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.on-site.collect', $reservation) }}"
                                              x-data @submit="$el.querySelector('button').disabled = true">
                                            @csrf
                                            <button type="submit" class="btn-accent !px-3 !py-1.5 text-xs">Tahsil edildi</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>
