<x-layouts.admin title="Devreler">

    <div x-data="{ editing: null }">
        <div class="mb-6">
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">Devreler</h1>
            <p class="page-subtitle">
                Devreler pazar girişle başlar, takip eden cumartesi sona erer. Yeterli müracaat olmayan devreler
                başvuruya kapatılabilir.
            </p>
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
                <p class="px-6 py-16 text-center text-sm text-stone-400">Bu tesis ve yıl için devre tanımlanmamış.</p>
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
                                <th>Başvuru</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($periods as $period)
                                <tr>
                                    <td>
                                        <span class="font-medium">{{ $period->number }}. Devre</span>
                                        @if ($period->is_discounted)
                                            <span class="badge-teal ml-1 !py-0.5 !text-[10px]">İndirimli</span>
                                        @endif
                                        @if ($period->note)
                                            <p class="mt-0.5 max-w-xs text-[11px] text-amber-700">{{ $period->note }}</p>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        {{ $period->start_date->translatedFormat('d M Y') }}<br>
                                        <span class="text-stone-500">{{ $period->end_date->translatedFormat('d M Y') }}</span>
                                    </td>
                                    <td>{{ $period->nights }}</td>
                                    <td class="text-xs">
                                        {{ $period->roomTariff?->name ?? '—' }}
                                        @if ($period->villaTariff)
                                            <p class="text-stone-500">{{ $period->villaTariff->name }}</p>
                                        @endif
                                    </td>
                                    <td class="text-xs text-stone-500">{{ $period->combine_group ? 'Grup ' . $period->combine_group : '—' }}</td>
                                    <td>{{ $counts[$period->id] ?? 0 }}</td>
                                    <td>
                                        <span class="badge-{{ $period->is_open ? 'green' : 'gray' }}">{{ $period->is_open ? 'Açık' : 'Kapalı' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
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
                                                <button class="btn-ghost !px-2.5 !py-1 text-xs {{ $period->is_open ? '!text-red-600' : '!text-teal-700' }}">
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
                    <h3 class="mb-4 font-display text-lg font-semibold text-navy-900" x-text="editing?.label + ' düzenle'"></h3>
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
                                <input type="checkbox" name="is_discounted" value="1" x-model="editing.is_discounted" class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                                İndirimli devre
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="hidden" name="is_open" value="0">
                                <input type="checkbox" name="is_open" value="1" x-model="editing.is_open" class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
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
