<x-layouts.admin title="Oda Envanteri">

    <div x-data="{ editing: null }">
        <div class="mb-6">
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">Oda envanteri</h1>
            <p class="page-subtitle">Blok ve oda numarasıyla tanımlı fiziksel odalar. Bakımdaki odaları pasife alın; oda tipi adetleri buradan türetilir.</p>
        </div>

        {{-- Tesis seçimi --}}
        <div class="mb-5 flex flex-wrap gap-2">
            @foreach ($facilities as $item)
                <a href="{{ route('admin.rooms.index', ['tesis' => $item->slug]) }}"
                   class="{{ $facility?->is($item) ? 'btn-primary' : 'btn-secondary' }} !px-4 !py-1.5 text-xs">
                    {{ $item->name }}
                    <span class="ml-1.5 opacity-70">{{ $item->rooms_count }}</span>
                </a>
            @endforeach
        </div>

        @if (! $facility)
            <div class="surface p-10 text-center text-sm text-ink-muted">Tanımlı tesis yok.</div>
        @elseif ($facility->rooms_count === 0)
            <div class="surface p-10 text-center">
                <p class="text-sm font-medium text-ink">{{ $facility->name }} için oda kaydı yok.</p>
                <p class="mt-1.5 text-xs text-ink-muted">
                    Oda listesi Excel dosyasını
                    <code class="rounded bg-surface-sunken px-1.5 py-0.5 text-[11px]">php artisan ssk:import-rooms "ODALAR.xlsx" --facility={{ $facility->slug }}</code>
                    komutuyla aktarın.
                </p>
                <p class="mt-3 text-xs text-ink-subtle">
                    Envanter girilene dek kapasite, oda tiplerindeki adet değerlerinden okunur.
                </p>
            </div>
        @else
            {{-- Oda tipi özeti --}}
            <div class="surface mb-5 overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Oda tipi</th>
                            <th>Yatak</th>
                            <th>Özellik</th>
                            <th class="text-right">Aktif</th>
                            <th class="text-right">Toplam</th>
                            <th class="text-right">Yatak kapasitesi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($roomTypes as $type)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.rooms.index', ['tesis' => $facility->slug, 'tip' => $type->id]) }}"
                                       class="font-medium hover:text-accent-500">{{ $type->name }}</a>
                                </td>
                                <td class="text-xs tabular-nums">{{ $type->bed_count }}</td>
                                <td>
                                    @if ($type->is_ground_floor)
                                        <span class="badge-amber">Zemin kat · %10</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $type->active_rooms_count }}</td>
                                <td class="text-right tabular-nums text-ink-muted">{{ $type->rooms_count }}</td>
                                <td class="text-right tabular-nums">{{ $type->active_rooms_count * $type->bed_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-line font-semibold">
                            <td colspan="3">Toplam</td>
                            <td class="text-right tabular-nums">{{ $roomTypes->sum('active_rooms_count') }}</td>
                            <td class="text-right tabular-nums text-ink-muted">{{ $roomTypes->sum('rooms_count') }}</td>
                            <td class="text-right tabular-nums">{{ $roomTypes->sum(fn ($t) => $t->active_rooms_count * $t->bed_count) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Filtreler --}}
            <form method="GET" class="surface mb-5 flex flex-wrap items-end gap-3 p-4">
                <input type="hidden" name="tesis" value="{{ $facility->slug }}">
                <div>
                    <label class="field-label">Blok</label>
                    <select name="blok" class="field-input !py-1.5 text-xs">
                        <option value="">Tümü</option>
                        @foreach ($allBlocks as $block)
                            <option value="{{ $block }}" @selected(request('blok') === $block)>{{ $block }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Oda tipi</label>
                    <select name="tip" class="field-input !py-1.5 text-xs">
                        <option value="">Tümü</option>
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) request('tip') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Durum</label>
                    <select name="durum" class="field-input !py-1.5 text-xs">
                        <option value="">Tümü</option>
                        <option value="active" @selected(request('durum') === 'active')>Aktif</option>
                        <option value="passive" @selected(request('durum') === 'passive')>Pasif</option>
                    </select>
                </div>
                <button class="btn-primary !px-4 !py-1.5 text-xs">Filtrele</button>
                @if (request()->hasAny(['blok', 'tip', 'durum']))
                    <a href="{{ route('admin.rooms.index', ['tesis' => $facility->slug]) }}" class="btn-ghost !px-3 !py-1.5 text-xs">Temizle</a>
                @endif
                <span class="ml-auto text-xs text-ink-muted">{{ $totalRooms }} oda listeleniyor</span>
            </form>

            {{-- Bloklar --}}
            <div class="space-y-4">
                @forelse ($blocks as $blockName => $rooms)
                    <div class="surface overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
                            <h2 class="font-display text-sm font-semibold text-ink">{{ $blockName }}</h2>
                            <p class="text-[11px] text-ink-muted">
                                {{ $rooms->count() }} oda ·
                                {{ $rooms->groupBy(fn ($r) => $r->roomType->name)->map->count()
                                    ->map(fn ($n, $name) => "{$n}× {$name}")->join(' · ') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                            @foreach ($rooms as $room)
                                <button type="button"
                                        @click="editing = {{ Illuminate\Support\Js::from([
                                            'id' => $room->id,
                                            'label' => $room->label(),
                                            'is_active' => $room->is_active,
                                            'note' => $room->note,
                                            'room_type_id' => $room->room_type_id,
                                        ]) }}"
                                        class="rounded-lg border px-3 py-2 text-left transition-colors
                                               {{ $room->is_active
                                                   ? 'border-line bg-surface-sunken hover:border-accent-400'
                                                   : 'border-dashed border-line-soft bg-transparent opacity-60 hover:opacity-100' }}">
                                    <p class="text-sm font-semibold tabular-nums text-ink">{{ $room->number }}</p>
                                    <p class="truncate text-[10px] text-ink-muted">{{ $room->roomType->name }}</p>
                                    @if (! $room->is_active)
                                        <p class="mt-0.5 text-[10px] font-medium text-amber-600 dark:text-amber-400">Pasif</p>
                                    @endif
                                    @if ($room->note)
                                        <p class="truncate text-[10px] text-ink-subtle" title="{{ $room->note }}">{{ $room->note }}</p>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="surface p-10 text-center text-sm text-ink-muted">Bu filtreyle eşleşen oda yok.</div>
                @endforelse
            </div>
        @endif

        {{-- Oda düzenleme modalı --}}
        <template x-teleport="body">
            <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editing = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink" x-text="editing?.label"></h3>
                    <form method="POST" :action="'{{ url('admin/odalar') }}/' + editing?.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="field-label">Oda tipi</label>
                            <select name="room_type_id" x-model.number="editing.room_type_id" class="field-input">
                                @foreach ($roomTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Not</label>
                            <input type="text" name="note" x-model="editing.note" maxlength="255"
                                   placeholder="Örn. klima arızası — 20 Ağustos'a kadar" class="field-input">
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="editing.is_active"
                                   class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            Envanterde aktif
                        </label>
                        <p class="text-[11px] text-ink-subtle">Pasife alınan oda, oda tipinin adedinden düşülür.</p>
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
