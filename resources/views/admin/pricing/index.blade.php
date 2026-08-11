<x-layouts.admin title="Fiyatlandırma">

    <div class="mb-8">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Fiyatlandırma</h1>
        <p class="page-subtitle">Müşteri sınıflarına göre günlük konaklama fiyatlarını yönetin. Değişiklikler yeni rezervasyonlara anında yansır.</p>
    </div>

    @php $reopenClassId = collect($classes)->first(fn ($c) => $errors->{'edit-'.$c->id}->any())?->id; @endphp

    <div class="grid gap-5 sm:grid-cols-3" x-data="{ editing: {{ $reopenClassId ?? 'null' }} }">
        @foreach ($classes as $i => $class)
            @php
                $cErrors = $errors->{'edit-'.$class->id};
                $cOld = fn ($key, $default) => $cErrors->any() ? old($key, $default) : $default;
            @endphp
            <div class="surface surface-hover p-6 animate-rise" style="animation-delay: {{ $i * 70 }}ms">
                <p class="section-label">{{ $class->name }}</p>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">{{ $class->description }}</p>

                <div class="mt-6" x-show="editing !== {{ $class->id }}">
                    <p class="font-display text-3xl font-semibold tracking-tight text-navy-900">
                        ₺{{ number_format($class->daily_price, 0, ',', '.') }}
                        <span class="text-base font-sans font-medium text-stone-400">/ gün</span>
                    </p>
                    <button @click="editing = {{ $class->id }}" class="btn-secondary mt-5 w-full">Fiyatı Düzenle</button>
                </div>

                <form method="POST" action="{{ route('admin.pricing.update', $class) }}" x-show="editing === {{ $class->id }}" x-cloak class="mt-6 space-y-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $class->name }}">
                    <div>
                        <label class="field-label">Günlük fiyat (₺)</label>
                        <input type="number" name="daily_price" step="0.01" min="0" value="{{ $cOld('daily_price', $class->daily_price) }}" class="field-input @error('daily_price', 'edit-'.$class->id) !border-red-400 @enderror">
                        @error('daily_price', 'edit-'.$class->id) <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label">Açıklama</label>
                        <input type="text" name="description" value="{{ $cOld('description', $class->description) }}" class="field-input @error('description', 'edit-'.$class->id) !border-red-400 @enderror">
                        @error('description', 'edit-'.$class->id) <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="button" @click="editing = null" class="btn-secondary flex-1">Vazgeç</button>
                        <button type="submit" class="btn-accent flex-1">Kaydet</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
