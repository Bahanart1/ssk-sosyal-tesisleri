<x-layouts.admin title="Tesis & Odalar">

    <div x-data="{ editingRoom: {}, newRoomFor: null, editingFacility: {} }">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="section-label">Yönetim</p>
                <h1 class="page-title mt-1">Tesisler ve oda tipleri</h1>
                <p class="page-subtitle">Konaklama seçeneklerini, yatak sayılarını ve envanteri yönetin.</p>
            </div>
        </div>

        <div class="space-y-6">
            @foreach ($facilities as $facility)
                <div class="surface overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-6 py-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-display text-lg font-semibold text-ink">{{ $facility->name }}</h2>
                                <span class="badge-{{ $facility->is_active ? 'green' : 'gray' }}">{{ $facility->is_active ? 'Aktif' : 'Pasif' }}</span>
                            </div>
                            <p class="text-xs text-ink-muted">{{ $facility->location }} · {{ $facility->reservations_count }} başvuru</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs"
                                    @click="editingFacility = {{ Illuminate\Support\Js::from($facility->only(['id', 'name', 'location', 'description', 'is_active'])) }}">
                                Tesisi düzenle
                            </button>
                            <button type="button" @click="newRoomFor = {{ $facility->id }}" class="btn-secondary !px-3 !py-1.5 text-xs">
                                Oda tipi ekle
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Oda tipi</th>
                                    <th>Tür</th>
                                    <th>Yatak</th>
                                    <th>Azami kişi</th>
                                    <th>Adet</th>
                                    <th>Özellik</th>
                                    <th>Durum</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($facility->roomTypes as $roomType)
                                    <tr>
                                        <td>
                                            <p class="font-medium">{{ $roomType->name }}</p>
                                            <p class="max-w-sm text-[11px] text-ink-muted">{{ $roomType->description }}</p>
                                        </td>
                                        <td class="text-xs">{{ $roomType->kind === 'villa' ? 'Villa' : 'Oda' }}</td>
                                        <td>{{ $roomType->bed_count }}</td>
                                        <td>{{ $roomType->capacity() }}</td>
                                        <td>{{ $roomType->quantity }}</td>
                                        <td class="space-y-1 text-[11px]">
                                            @if ($roomType->is_ground_floor)
                                                <span class="badge-teal !py-0.5 !text-[10px]">Zemin kat · %10</span>
                                            @endif
                                            @if ($roomType->min_billed_persons)
                                                <span class="badge-amber !py-0.5 !text-[10px]">En az {{ $roomType->min_billed_persons }} kişi</span>
                                            @endif
                                            @if ($roomType->waive_empty_bed_at_occupancy)
                                                <span class="badge-gray !py-0.5 !text-[10px]">{{ $roomType->waive_empty_bed_at_occupancy }} kişide boş yatak ücretsiz</span>
                                            @endif
                                        </td>
                                        <td><span class="badge-{{ $roomType->is_active ? 'green' : 'gray' }}">{{ $roomType->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                                        <td class="text-right">
                                            <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                                                    @click="editingRoom = {{ Illuminate\Support\Js::from($roomType->only([
                                                        'id', 'name', 'kind', 'bed_count', 'is_ground_floor', 'min_billed_persons',
                                                        'max_persons', 'waive_empty_bed_at_occupancy', 'quantity', 'description', 'is_active',
                                                    ])) }}">Düzenle</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="py-10 text-center text-sm text-ink-subtle">Oda tipi tanımlanmamış.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Yeni tesis --}}
        <div x-data="{ open: {{ $errors->facility->any() ? 'true' : 'false' }} }" class="surface mt-6 overflow-hidden">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-6 py-4 text-left">
                <span class="font-display text-lg font-semibold text-ink">Yeni tesis ekle</span>
                <svg class="h-5 w-5 text-ink-subtle transition-transform" :class="open ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('admin.facilities.store') }}" class="grid gap-4 border-t border-line p-6 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="field-label">Tesis adı</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="field-input">
                    @error('name', 'facility') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">Konum</label>
                    <input type="text" name="location" value="{{ old('location') }}" class="field-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Açıklama</label>
                    <textarea name="description" rows="2" class="field-input">{{ old('description') }}</textarea>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                    Aktif
                </label>
                <div class="sm:col-span-2"><button class="btn-primary">Tesis Ekle</button></div>
            </form>
        </div>

        {{-- Tesis düzenleme modalı --}}
        <template x-teleport="body">
            <div x-show="editingFacility.id" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editingFacility = {}"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink">Tesisi düzenle</h3>
                    <form method="POST" :action="'{{ url('admin/tesisler') }}/' + editingFacility?.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="field-label">Tesis adı</label>
                            <input type="text" name="name" x-model="editingFacility.name" required class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Konum</label>
                            <input type="text" name="location" x-model="editingFacility.location" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Açıklama</label>
                            <textarea name="description" rows="3" x-model="editingFacility.description" class="field-input"></textarea>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="editingFacility.is_active" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            Aktif
                        </label>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="editingFacility = {}" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Oda tipi düzenleme modalı --}}
        <template x-teleport="body">
            <div x-show="editingRoom.id" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editingRoom = {}"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink">Oda tipini düzenle</h3>
                    <form method="POST" :action="'{{ url('admin/oda-tipleri') }}/' + editingRoom?.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        @include('admin.facilities._room-type-fields', ['model' => 'editingRoom'])
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="editingRoom = {}" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Yeni oda tipi modalı --}}
        <template x-teleport="body">
            <div x-show="newRoomFor" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="newRoomFor = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink">Yeni oda tipi</h3>
                    <form method="POST" :action="'{{ url('admin/tesisler') }}/' + newRoomFor + '/oda-tipleri'" class="space-y-4">
                        @csrf
                        <div>
                            <label class="field-label">Oda tipi adı</label>
                            <input type="text" name="name" required class="field-input">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Tür</label>
                                <select name="kind" class="field-input">
                                    <option value="room">Oda</option>
                                    <option value="villa">Villa</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Yatak sayısı</label>
                                <input type="number" name="bed_count" min="1" max="20" value="2" required class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Azami kişi</label>
                                <input type="number" name="max_persons" min="1" max="20" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Adet</label>
                                <input type="number" name="quantity" min="1" value="1" required class="field-input">
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Açıklama</label>
                            <textarea name="description" rows="2" class="field-input"></textarea>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" name="is_ground_floor" value="1" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            Zemin kat (%10 indirim uygulanır)
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" checked class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            Aktif
                        </label>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="newRoomFor = null" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
