<x-layouts.admin title="Müşteriler">

    <div x-data="{ createOpen: false, editOpen: false, editing: null }">

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
                <p class="px-6 py-16 text-center text-sm text-slate-400">Kayıtlı müşteri bulunamadı.</p>
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
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($customers as $c)
                                <tr>
                                    <td class="font-medium">{{ $c->name }}</td>
                                    <td class="text-slate-500">{{ $c->maskedTcNo() }}</td>
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

                <ul class="divide-y divide-slate-100 lg:hidden">
                    @foreach ($customers as $c)
                        <li class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-semibold text-navy-900">{{ $c->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $c->maskedTcNo() }} · {{ $c->customerClass?->name ?? '-' }}</p>
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
        <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 overflow-y-auto">
            <div class="modal-scrim" @click="createOpen = false"></div>
            <div class="modal-panel" x-transition>
                <h3 class="mb-4 font-display text-lg font-semibold text-navy-900">Yeni müşteri hesabı</h3>
                <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-4">
                    @csrf
                    <div><label class="field-label">Ad Soyad</label><input type="text" name="name" required class="field-input"></div>
                    <div><label class="field-label">TC Kimlik No</label><input type="text" name="tc_no" maxlength="11" required class="field-input"></div>
                    <div><label class="field-label">Telefon</label><input type="text" name="phone" class="field-input"></div>
                    <div>
                        <label class="field-label">Müşteri Sınıfı</label>
                        <select name="customer_class_id" required class="field-input">
                            @foreach ($classes as $cls)
                                <option value="{{ $cls->id }}">{{ $cls->name }} — ₺{{ number_format($cls->daily_price, 0, ',', '.') }}/gün</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="field-label">Geçici Şifre</label><input type="password" name="password" required class="field-input" autocomplete="new-password"></div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="createOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                        <button type="submit" class="btn-primary flex-1">Oluştur</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Müşteri düzenleme modalı -->
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 overflow-y-auto">
            <div class="modal-scrim" @click="editOpen = false"></div>
            <template x-if="editing">
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-navy-900">Müşteriyi düzenle</h3>
                    <form method="POST" :action="'/admin/musteriler/' + editing.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div><label class="field-label">Ad Soyad</label><input type="text" name="name" x-model="editing.name" required class="field-input"></div>
                        <div><label class="field-label">Telefon</label><input type="text" name="phone" x-model="editing.phone" class="field-input"></div>
                        <div>
                            <label class="field-label">Müşteri Sınıfı</label>
                            <select name="customer_class_id" x-model="editing.customer_class_id" required class="field-input">
                                @foreach ($classes as $cls)
                                    <option value="{{ $cls->id }}">{{ $cls->name }} — ₺{{ number_format($cls->daily_price, 0, ',', '.') }}/gün</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="field-label">Yeni Şifre (opsiyonel)</label><input type="password" name="password" class="field-input" placeholder="Boş bırakılırsa değişmez" autocomplete="new-password"></div>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="is_active" value="1" x-model="editing.is_active" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
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
    </div>
</x-layouts.admin>
