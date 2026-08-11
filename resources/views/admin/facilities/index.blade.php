<x-layouts.admin title="Tesisler">

    <div x-data="{ createOpen: {{ $errors->create->any() ? 'true' : 'false' }} }">
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="section-label">Yönetim</p>
                <h1 class="page-title mt-1">Tesisler</h1>
                <p class="page-subtitle">Rezervasyona açık sosyal tesisleri yönetin.</p>
            </div>
            <button @click="createOpen = true" class="btn-primary shrink-0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Yeni Tesis
            </button>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($facilities as $i => $f)
                @php
                    $fErrors = $errors->{'edit-'.$f->id};
                    $fOld = fn ($key, $default) => $fErrors->any() ? old($key, $default) : $default;
                @endphp
                <div class="surface surface-hover overflow-hidden animate-rise" style="animation-delay: {{ $i * 50 }}ms" x-data="{ editOpen: {{ $fErrors->any() ? 'true' : 'false' }} }">
                    <div class="h-1.5 bg-gradient-to-r from-navy-800 via-teal-500 to-teal-300"></div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-display text-lg font-semibold text-navy-900">{{ $f->name }}</p>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $f->location }}</p>
                            </div>
                            <span class="badge-{{ $f->is_active ? 'green' : 'gray' }}">{{ $f->is_active ? 'Aktif' : 'Pasif' }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-stone-500 line-clamp-2">{{ $f->description }}</p>
                        <div class="mt-4 flex items-center justify-between text-xs text-stone-400">
                            <span>Kapasite: {{ $f->capacity }} kişi</span>
                            <span>{{ $f->reservations_count }} rezervasyon</span>
                        </div>
                        <button @click="editOpen = true" class="btn-secondary mt-4 w-full">Düzenle</button>
                    </div>

                    <template x-teleport="body">
                        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                            <div class="modal-scrim" @click="editOpen = false"></div>
                            <div class="modal-panel" x-transition>
                                <h3 class="font-display text-lg font-semibold text-navy-900">Tesisi düzenle</h3>
                                <form method="POST" action="{{ route('admin.facilities.update', $f) }}" class="mt-4 space-y-4">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="field-label">Tesis adı</label>
                                        <input type="text" name="name" value="{{ $fOld('name', $f->name) }}" required class="field-input @error('name', 'edit-'.$f->id) !border-red-400 @enderror">
                                        @error('name', 'edit-'.$f->id) <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="field-label">Konum</label>
                                        <input type="text" name="location" value="{{ $fOld('location', $f->location) }}" class="field-input @error('location', 'edit-'.$f->id) !border-red-400 @enderror">
                                        @error('location', 'edit-'.$f->id) <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="field-label">Açıklama</label>
                                        <textarea name="description" rows="3" class="field-input @error('description', 'edit-'.$f->id) !border-red-400 @enderror">{{ $fOld('description', $f->description) }}</textarea>
                                        @error('description', 'edit-'.$f->id) <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="field-label">Kapasite</label>
                                        <input type="number" name="capacity" min="1" value="{{ $fOld('capacity', $f->capacity) }}" class="field-input @error('capacity', 'edit-'.$f->id) !border-red-400 @enderror">
                                        @error('capacity', 'edit-'.$f->id) <p class="field-error">{{ $message }}</p> @enderror
                                    </div>
                                    <label class="flex items-center gap-2 text-sm text-stone-600">
                                        <input type="checkbox" name="is_active" value="1" {{ $fOld('is_active', $f->is_active) ? 'checked' : '' }} class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                                        Rezervasyona açık
                                    </label>
                                    <div class="flex gap-3 pt-2">
                                        <button type="button" @click="editOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                                        <button type="submit" class="btn-primary flex-1">Kaydet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>
            @endforeach
        </div>

        <template x-teleport="body">
            <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="createOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-navy-900">Yeni tesis ekle</h3>
                    <form method="POST" action="{{ route('admin.facilities.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label class="field-label">Tesis adı</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="field-input @error('name', 'create') !border-red-400 @enderror">
                            @error('name', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Konum</label>
                            <input type="text" name="location" value="{{ old('location') }}" class="field-input @error('location', 'create') !border-red-400 @enderror">
                            @error('location', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Açıklama</label>
                            <textarea name="description" rows="3" class="field-input @error('description', 'create') !border-red-400 @enderror">{{ old('description') }}</textarea>
                            @error('description', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Kapasite</label>
                            <input type="number" name="capacity" min="1" value="{{ old('capacity', 2) }}" class="field-input @error('capacity', 'create') !border-red-400 @enderror">
                            @error('capacity', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="createOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
