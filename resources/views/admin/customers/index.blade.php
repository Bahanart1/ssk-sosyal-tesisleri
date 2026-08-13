<x-layouts.admin title="Üyeler">

    @php
        $reopenCreate = $errors->create->any();
        $reopenEdit = $errors->edit->any();
    @endphp

    <div x-data="{ createOpen: {{ $reopenCreate ? 'true' : 'false' }}, editing: null }">

        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="section-label">Yönetim</p>
                <h1 class="page-title mt-1">Üyeler</h1>
                <p class="page-subtitle">Giriş bilgilerini oluşturun, grup ve aidat durumunu yönetin.</p>
            </div>
            <button @click="createOpen = true" class="btn-primary shrink-0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Yeni Üye
            </button>
        </div>

        <form method="GET" class="surface mb-6 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[14rem] flex-1">
                <label class="field-label">Ara</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Ad, TC no veya üyelik no" class="field-input">
            </div>
            <div>
                <label class="field-label">Grup</label>
                <select name="group" class="field-input">
                    <option value="">Tümü</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected(request('group') == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Aidat</label>
                <select name="dues" class="field-input">
                    <option value="">Tümü</option>
                    <option value="debt" @selected(request('dues') === 'debt')>Borçlu</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filtrele</button>
            @if (request()->hasAny(['q', 'group', 'dues']))
                <a href="{{ route('admin.customers.index') }}" class="btn-ghost">Temizle</a>
            @endif
        </form>

        <div class="surface overflow-hidden">
            @if ($customers->isEmpty())
                <p class="px-6 py-16 text-center text-sm text-stone-400">Kayıtlı üye bulunamadı.</p>
            @else
                <div class="hidden overflow-x-auto lg:block">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th>Üyelik No</th>
                                <th>TC No</th>
                                <th>Telefon</th>
                                <th>Grup</th>
                                <th>Aidat</th>
                                <th>Başvuru</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($customers as $customer)
                                <tr>
                                    <td class="font-medium">{{ $customer->name }}</td>
                                    <td class="text-xs">{{ $customer->membership_no ?? '-' }}</td>
                                    <td class="font-mono text-xs text-stone-500">{{ $customer->maskedTcNo() }}</td>
                                    <td class="text-xs">{{ $customer->phone ?? '-' }}</td>
                                    <td class="text-xs">{{ $customer->customerGroup?->name ?? '-' }}</td>
                                    <td>
                                        @if ($customer->hasDuesDebt())
                                            <div class="flex items-center gap-2">
                                                <span class="badge-red">Borçlu</span>
                                                <form method="POST" action="{{ route('admin.customers.dues', $customer) }}">
                                                    @csrf
                                                    <button class="text-[11px] font-semibold text-teal-700 hover:text-teal-800">Ödendi işaretle</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="badge-green">{{ $customer->dues_paid_year ?? 'Muaf' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $customer->reservations_count }}</td>
                                    <td><span class="badge-{{ $customer->is_active ? 'green' : 'gray' }}">{{ $customer->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                                    <td class="text-right">
                                        <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                                                @click="editing = {{ Illuminate\Support\Js::from($customer->only([
                                                    'id', 'name', 'membership_no', 'tc_no', 'phone', 'email',
                                                    'customer_group_id', 'dues_paid_year', 'is_active',
                                                ])) }}">Düzenle</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="divide-y divide-stone-100 lg:hidden">
                    @foreach ($customers as $customer)
                        <li class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-navy-900">{{ $customer->name }}</p>
                                    <p class="text-xs text-stone-500">
                                        {{ $customer->membership_no ?? $customer->maskedTcNo() }} · {{ $customer->customerGroup?->name ?? '-' }}
                                    </p>
                                </div>
                                @if ($customer->hasDuesDebt())
                                    <span class="badge-red">Borçlu</span>
                                @else
                                    <span class="badge-{{ $customer->is_active ? 'green' : 'gray' }}">{{ $customer->is_active ? 'Aktif' : 'Pasif' }}</span>
                                @endif
                            </div>
                            <button type="button" class="btn-secondary mt-3 w-full"
                                    @click="editing = {{ Illuminate\Support\Js::from($customer->only([
                                        'id', 'name', 'membership_no', 'tc_no', 'phone', 'email',
                                        'customer_group_id', 'dues_paid_year', 'is_active',
                                    ])) }}">Düzenle</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="mt-6">{{ $customers->links() }}</div>

        {{-- Yeni üye --}}
        <template x-teleport="body">
            <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="createOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-navy-900">Yeni üye hesabı</h3>
                    <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="field-label">Ad Soyad</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="field-input @error('name', 'create') !border-red-400 @enderror">
                            @error('name', 'create') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">TC Kimlik No</label>
                                <input type="text" name="tc_no" maxlength="11" value="{{ old('tc_no') }}" required class="field-input @error('tc_no', 'create') !border-red-400 @enderror">
                                @error('tc_no', 'create') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Üyelik No</label>
                                <input type="text" name="membership_no" value="{{ old('membership_no') }}" class="field-input">
                                @error('membership_no', 'create') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Telefon</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">E-posta</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="field-input">
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Müşteri grubu</label>
                            <select name="customer_group_id" required class="field-input">
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected(old('customer_group_id') == $group->id)>{{ $group->name }} — {{ $group->description }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Aidatın ödendiği yıl</label>
                                <input type="number" name="dues_paid_year" min="2000" max="2100" value="{{ old('dues_paid_year', now()->year) }}" class="field-input">
                                <p class="field-hint">Boş bırakılırsa üye borçlu sayılır.</p>
                            </div>
                            <div>
                                <label class="field-label">Şifre</label>
                                <input type="text" name="password" required minlength="6" class="field-input @error('password', 'create') !border-red-400 @enderror">
                                @error('password', 'create') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="createOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Oluştur</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Üye düzenleme --}}
        <template x-teleport="body">
            <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editing = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-navy-900">Üyeyi düzenle</h3>
                    <form method="POST" :action="'{{ url('admin/uyeler') }}/' + editing?.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="field-label">Ad Soyad</label>
                            <input type="text" name="name" x-model="editing.name" required class="field-input">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">TC Kimlik No</label>
                                <input type="text" name="tc_no" maxlength="11" x-model="editing.tc_no" required class="field-input font-mono">
                            </div>
                            <div>
                                <label class="field-label">Üyelik No</label>
                                <input type="text" name="membership_no" x-model="editing.membership_no" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Telefon</label>
                                <input type="text" name="phone" x-model="editing.phone" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">E-posta</label>
                                <input type="email" name="email" x-model="editing.email" class="field-input">
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Müşteri grubu</label>
                            <select name="customer_group_id" x-model.number="editing.customer_group_id" required class="field-input">
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }} — {{ $group->description }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Aidatın ödendiği yıl</label>
                                <input type="number" name="dues_paid_year" min="2000" max="2100" x-model.number="editing.dues_paid_year" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Yeni şifre</label>
                                <input type="text" name="password" minlength="6" placeholder="Değiştirmek istemiyorsanız boş bırakın" class="field-input">
                            </div>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="editing.is_active" class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                            Hesap aktif
                        </label>
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
