<x-layouts.admin title="Devreler">

    <div x-data="{ editing: null }">
        <div class="mb-6">
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">Devreler</h1>
            <p class="page-subtitle">
                Devreler pazar girişle başlar, takip eden cumartesi sona erer. Doluluk oranı yalnızca
                <strong>yer tahsis edilen</strong> başvurulardan hesaplanır; bekleyenler henüz kapasiteyi
                işgal etmez. Dolan devreler başvuruya kapatılabilir.
            </p>

            @if ($facility)
                <p class="mt-2 text-xs text-ink-muted">
                    {{ $facility->name }} kapasitesi:
                    <span class="font-semibold text-ink">{{ $capacity['count'] }} ünite</span>
                    <span class="text-ink-subtle">({{ $capacity['source'] }})</span>
                    @if ($capacity['source'] === 'oda tipi adetleri')
                        · <span class="text-amber-700 dark:text-amber-300">bu tesis için fiziksel oda envanteri henüz aktarılmadı</span>
                    @endif
                </p>
            @endif
        </div>

        {{-- Tesis / yıl seçimi --}}
        <form method="GET" class="surface mb-6 flex flex-wrap items-end gap-3 p-4">
            <div>
                <label class="field-label">Tesis</label>
                <select name="facility" class="field-input" onchange="this.form.submit()">
                    @foreach ($facilities as $item)
                        <option value="{{ $item->id }}" @selected($facility?->id === $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Yıl</label>
                <select name="year" class="field-input" onchange="this.form.submit()">
                    @foreach ($years as $option)
                        <option value="{{ $option }}" @selected($year === (int) $option)>{{ $option }}</option>
                    @endforeach
                    @if (! $years->contains($year))
                        <option value="{{ $year }}" selected>{{ $year }}</option>
                    @endif
                </select>
            </div>
            <noscript><button class="btn-primary">Göster</button></noscript>
        </form>

        <div class="surface overflow-hidden">
            @if ($periods->isEmpty())
                <p class="px-6 py-16 text-center text-sm text-ink-subtle">Bu tesis ve yıl için devre tanımlanmamış.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Devre</th>
                                <th>Tarih</th>
                                <th>Gün</th>
                                <th>Tarife</th>
                                <th>Birleşim</th>
                                <th class="text-right">Tahsis</th>
                                <th class="text-right">Bekleyen</th>
                                <th>Doluluk</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($periods as $period)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.periods.show', $period) }}" class="font-medium text-ink hover:text-accent-600 dark:hover:text-accent-400">{{ $period->number }}. Devre</a>
                                        @if ($period->is_discounted)
                                            <span class="badge-accent ml-1 !py-0.5 !text-[10px]">İndirimli</span>
                                        @endif
                                        @if ($period->note)
                                            <p class="mt-0.5 max-w-xs text-[11px] text-amber-700">{{ $period->note }}</p>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        {{ $period->start_date->translatedFormat('d M Y') }}<br>
                                        <span class="text-ink-muted">{{ $period->end_date->translatedFormat('d M Y') }}</span>
                                    </td>
                                    <td>{{ $period->nights }}</td>
                                    <td class="text-xs">
                                        {{ $period->roomTariff?->name ?? '—' }}
                                        @if ($period->villaTariff)
                                            <p class="text-ink-muted">{{ $period->villaTariff->name }}</p>
                                        @endif
                                    </td>
                                    <td class="text-xs text-ink-muted">{{ $period->combine_group ? 'Grup ' . $period->combine_group : '—' }}</td>
                                    @php
                                        $tahsis  = $allocated[$period->id] ?? 0;
                                        $bekleyen = $pending[$period->id] ?? 0;
                                        $oran = $capacity['count'] > 0 ? min(1, $tahsis / $capacity['count']) : null;
                                    @endphp

                                    {{-- Yer tahsis edilenler: doluluğu bunlar belirler --}}
                                    <td class="text-right">
                                        <a href="{{ route('admin.periods.show', $period) }}"
                                           class="tabular-nums {{ $tahsis > 0 ? 'font-semibold text-ink hover:text-accent-700 dark:hover:text-accent-300' : 'text-ink-subtle' }}">
                                            {{ $tahsis }}
                                        </a>
                                    </td>

                                    {{-- Karara bağlanmamış başvurular --}}
                                    <td class="text-right">
                                        @if ($bekleyen > 0)
                                            <a href="{{ route('admin.periods.show', $period) }}" class="badge-amber tabular-nums">{{ $bekleyen }}</a>
                                        @else
                                            <span class="text-ink-subtle">—</span>
                                        @endif
                                    </td>

                                    {{-- Doluluk oranı: devreyi kapatma kararı buna göre verilir --}}
                                    <td>
                                        @if ($oran === null)
                                            <span class="text-xs text-ink-subtle">kapasite yok</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-surface-sunken">
                                                    <div class="h-full rounded-full"
                                                         style="width: {{ max($oran * 100, $tahsis > 0 ? 2 : 0) }}%;
                                                                background: {{ $oran >= 1 ? 'var(--status-danger)' : ($oran >= 0.85 ? 'var(--status-warn)' : 'var(--chart-series)') }}"></div>
                                                </div>
                                                <span class="text-xs tabular-nums {{ $oran >= 1 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-ink-muted' }}">
                                                    %{{ round($oran * 100) }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge-{{ $period->is_open ? 'green' : 'gray' }}">{{ $period->is_open ? 'Açık' : 'Kapalı' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <a href="{{ route('admin.periods.show', $period) }}" class="btn-secondary !px-2.5 !py-1 text-xs">Aç</a>
                                            <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                                                    @click="editing = {{ Illuminate\Support\Js::from([
                                                        'id' => $period->id,
                                                        'label' => $period->number . '. Devre',
                                                        'start_date' => $period->start_date->toDateString(),
                                                        'nights' => $period->nights,
                                                        'is_discounted' => $period->is_discounted,
                                                        'is_open' => $period->is_open,
                                                        'combine_group' => $period->combine_group,
                                                        'room_tariff_id' => $period->room_tariff_id,
                                                        'villa_tariff_id' => $period->villa_tariff_id,
                                                        'note' => $period->note,
                                                    ]) }}">Düzenle</button>
                                            <form method="POST" action="{{ route('admin.periods.toggle', $period) }}">
                                                @csrf
                                                <button class="btn-ghost !px-2.5 !py-1 text-xs {{ $period->is_open ? '!text-red-600' : '!text-accent-700 dark:text-accent-300' }}">
                                                    {{ $period->is_open ? 'Kapat' : 'Aç' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Düzenleme modalı --}}
        <template x-teleport="body">
            <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editing = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink" x-text="editing?.label + ' düzenle'"></h3>
                    <form method="POST" :action="'{{ url('admin/devreler') }}/' + editing?.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Başlangıç tarihi</label>
                                <input type="date" name="start_date" x-model="editing.start_date" required class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Gün sayısı</label>
                                <input type="number" name="nights" min="1" max="30" x-model.number="editing.nights" required class="field-input">
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Oda tarifesi (Tablo 1)</label>
                            <select name="room_tariff_id" x-model.number="editing.room_tariff_id" required class="field-input">
                                @foreach ($roomTariffs as $tariff)
                                    <option value="{{ $tariff->id }}">{{ $tariff->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Villa tarifesi (Tablo 2)</label>
                            <select name="villa_tariff_id" x-model.number="editing.villa_tariff_id" class="field-input">
                                <option value="">Yok</option>
                                @foreach ($villaTariffs as $tariff)
                                    <option value="{{ $tariff->id }}">{{ $tariff->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Birleşim grubu</label>
                                <input type="number" name="combine_group" min="1" x-model.number="editing.combine_group" class="field-input">
                                <p class="field-hint">Aynı gruptaki ardışık devreler birleştirilebilir.</p>
                            </div>
                            <div>
                                <label class="field-label">Not</label>
                                <input type="text" name="note" maxlength="255" x-model="editing.note" class="field-input">
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="hidden" name="is_discounted" value="0">
                                <input type="checkbox" name="is_discounted" value="1" x-model="editing.is_discounted" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                                İndirimli devre
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="hidden" name="is_open" value="0">
                                <input type="checkbox" name="is_open" value="1" x-model="editing.is_open" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                                Başvuruya açık
                            </label>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="editing = null" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
