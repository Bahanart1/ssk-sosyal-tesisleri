<x-layouts.admin title="Müşteriler">

    @php
        $reopenCreate = $errors->create->any();
        $reopenEdit = $errors->edit->any();
        $reopenEditing = $reopenEdit ? [
            'id' => old('id'),
            'name' => old('name'),
            'phone' => old('phone'),
            'customer_class_id' => old('customer_class_id'),
            'is_active' => old('is_active') ? true : false,
        ] : null;
    @endphp

    <div x-data="{
        createOpen: {{ $reopenCreate ? 'true' : 'false' }},
        editOpen: {{ $reopenEdit ? 'true' : 'false' }},
        editing: {{ Illuminate\Support\Js::from($reopenEditing) }},
    }">

        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="section-label">Yönetim</p>
                <h1 class="page-title mt-1">Müşteriler</h1>
                <p class="page-subtitle">Müşteri hesaplarını oluşturun ve sınıf ataması yapın.</p>
            </div>
            <button @click="createOpen = true" class="btn-primary shrink-0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Yeni Müşteri
            </button>
        </div>

        <form method="GET" class="surface mb-6 flex items-center gap-3 p-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="İsim veya TC No ile ara..." class="field-input sm:max-w-xs">
            <button type="submit" class="btn-secondary">Ara</button>
        </form>

        <div class="surface overflow-hidden">
            @if ($customers->isEmpty())
                <p class="px-6 py-16 text-center text-sm text-stone-400">Kayıtlı müşteri bulunamadı.</p>
            @else
                <div class="hidden overflow-x-auto lg:block">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th>TC No</th>
                                <th>Telefon</th>
                                <th>Sınıf</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($customers as $c)
                                <tr>
                                    <td class="font-medium">{{ $c->name }}</td>
                                    <td class="text-stone-500">{{ $c->maskedTcNo() }}</td>
                                    <td>{{ $c->phone ?? '-' }}</td>
                                    <td>{{ $c->customerClass?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge-{{ $c->is_active ? 'green' : 'gray' }}">{{ $c->is_active ? 'Aktif' : 'Pasif' }}</span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            @click="editing = @js($c->only(['id','name','phone','customer_class_id','is_active'])); editOpen = true"
                                            class="btn-ghost !px-3 !py-1.5 text-xs">Düzenle</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="divide-y divide-stone-100 lg:hidden">
                    @foreach ($customers as $c)
                        <li class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-semibold text-navy-900">{{ $c->name }}</p>
                                    <p class="text-xs text-stone-500">{{ $c->maskedTcNo() }} · {{ $c->customerClass?->name ?? '-' }}</p>
                                </div>
                                <span class="badge-{{ $c->is_active ? 'green' : 'gray' }}">{{ $c->is_active ? 'Aktif' : 'Pasif' }}</span>
                            </div>
                            <button
                                type="button"
                                @click="editing = @js($c->only(['id','name','phone','customer_class_id','is_active'])); editOpen = true"
                                class="btn-secondary mt-3 w-full">Düzenle</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="mt-6">{{ $customers->links() }}</div>

        <!-- Yeni müşteri modalı -->
        <template x-teleport="body">
            <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 overflow-y-auto">
                <div class="modal-scrim" @click="createOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-navy-900">Yeni müşteri hesabı</h3>
                    <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="form" value="create">
                        <div>
                            <label class="field-label">Ad Soyad</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="field-input @error('name', 'create') !border-red-400 @enderror">
                            @error('name', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">TC Kimlik No</label>
                            <input type="text" name="tc_no" maxlength="11" value="{{ old('tc_no') }}" required class="field-input @error('tc_no', 'create') !border-red-400 @enderror">
                            @error('tc_no', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Telefon</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="field-input @error('phone', 'create') !border-red-400 @enderror">
                            @error('phone', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Müşteri Sınıfı</label>
                            <select name="customer_class_id" required class="field-input @error('customer_class_id', 'create') !border-red-400 @enderror">
                                @foreach ($classes as $cls)
                                    <option value="{{ $cls->id }}" @selected((string) old('customer_class_id') === (string) $cls->id)>{{ $cls->name }} — ₺{{ number_format($cls->daily_price, 0, ',', '.') }}/gün</option>
                                @endforeach
                            </select>
                            @error('customer_class_id', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Geçici Şifre</label>
                            <input type="password" name="password" required class="field-input @error('password', 'create') !border-red-400 @enderror" autocomplete="new-password">
                            @error('password', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="createOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Oluştur</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- Müşteri düzenleme modalı -->
        <template x-teleport="body">
            <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 overflow-y-auto">
                <div class="modal-scrim" @click="editOpen = false"></div>
                <template x-if="editing">
                    <div class="modal-panel" x-transition>
                        <h3 class="mb-4 font-display text-lg font-semibold text-navy-900">Müşteriyi düzenle</h3>
                        <form method="POST" :action="'/admin/musteriler/' + editing.id" class="space-y-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="form" value="edit">
                            <input type="hidden" name="id" x-model="editing.id">
                            <div>
                                <label class="field-label">Ad Soyad</label>
                                <input type="text" name="name" x-model="editing.name" required class="field-input @error('name', 'edit') !border-red-400 @enderror">
                                @error('name', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Telefon</label>
                                <input type="text" name="phone" x-model="editing.phone" class="field-input @error('phone', 'edit') !border-red-400 @enderror">
                                @error('phone', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Müşteri Sınıfı</label>
                                <select name="customer_class_id" x-model="editing.customer_class_id" required class="field-input @error('customer_class_id', 'edit') !border-red-400 @enderror">
                                    @foreach ($classes as $cls)
                                        <option value="{{ $cls->id }}">{{ $cls->name }} — ₺{{ number_format($cls->daily_price, 0, ',', '.') }}/gün</option>
                                    @endforeach
                                </select>
                                @error('customer_class_id', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Yeni Şifre (opsiyonel)</label>
                                <input type="password" name="password" class="field-input @error('password', 'edit') !border-red-400 @enderror" placeholder="Boş bırakılırsa değişmez" autocomplete="new-password">
                                @error('password', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <label class="flex items-center gap-2 text-sm text-stone-600">
                                <input type="checkbox" name="is_active" value="1" x-model="editing.is_active" class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                                Hesap aktif
                            </label>
                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="editOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                                <button type="submit" class="btn-primary flex-1">Kaydet</button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </template>
    </div>
</x-layouts.admin>
