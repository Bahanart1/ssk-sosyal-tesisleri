<x-layouts.admin title="Üyeler">

    <div x-data="{ createOpen: {{ $errors->create->any() ? 'true' : 'false' }} }">

        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="section-label">Yönetim</p>
                <h1 class="page-title mt-1">Üyeler</h1>
                <p class="page-subtitle">Giriş bilgilerini oluşturun; ayrıntı için bir üyeye tıklayın.</p>
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
            <div>
                <label class="field-label">Hesap</label>
                <select name="active" class="field-input">
                    <option value="">Tümü</option>
                    <option value="passive" @selected(request('active') === 'passive')>Pasif</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filtrele</button>
            @if (request()->hasAny(['q', 'group', 'dues', 'active']))
                <a href="{{ route('admin.customers.index') }}" class="btn-ghost">Temizle</a>
            @endif
        </form>

        <div class="surface overflow-hidden">
            @if ($customers->isEmpty())
                <p class="px-6 py-16 text-center text-sm text-ink-subtle">Kayıtlı üye bulunamadı.</p>
            @else
                {{-- Masaüstü --}}
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
                        <tbody class="divide-y divide-line">
                            @foreach ($customers as $customer)
                                <tr class="cursor-pointer" onclick="window.location='{{ route('admin.customers.show', $customer) }}'">
                                    <td>
                                        <a href="{{ route('admin.customers.show', $customer) }}"
                                           class="font-medium text-ink hover:text-accent-700 dark:hover:text-accent-300">{{ $customer->name }}</a>
                                    </td>
                                    <td class="text-xs">{{ $customer->membership_no ?? '-' }}</td>
                                    <td class="font-mono text-xs text-ink-muted">{{ $customer->maskedTcNo() }}</td>
                                    <td class="text-xs">{{ $customer->phone ?? '-' }}</td>
                                    <td class="text-xs">{{ $customer->customerGroup?->name ?? '-' }}</td>
                                    <td>
                                        @if (! $customer->customerGroup?->requires_membership)
                                            <span class="badge-gray">Muaf</span>
                                        @elseif ($customer->unpaid_dues_count > 0)
                                            <span class="badge-red">{{ $customer->unpaid_dues_count }} yıl borçlu</span>
                                        @else
                                            <span class="badge-green">Güncel</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums">{{ $customer->reservations_count }}</td>
                                    <td><span class="badge-{{ $customer->is_active ? 'green' : 'gray' }}">{{ $customer->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="btn-ghost !px-2.5 !py-1 text-xs">Aç</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobil --}}
                <ul class="divide-y divide-line lg:hidden">
                    @foreach ($customers as $customer)
                        <li>
                            <a href="{{ route('admin.customers.show', $customer) }}"
                               class="block p-4 transition-colors hover:bg-accent-50/60 dark:hover:bg-accent-900/20">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-ink">{{ $customer->name }}</p>
                                        <p class="text-xs text-ink-muted">
                                            {{ $customer->membership_no ?? $customer->maskedTcNo() }} · {{ $customer->customerGroup?->name ?? '-' }}
                                        </p>
                                    </div>
                                    @if (! $customer->customerGroup?->requires_membership)
                                        <span class="badge-gray">Muaf</span>
                                    @elseif ($customer->unpaid_dues_count > 0)
                                        <span class="badge-red">{{ $customer->unpaid_dues_count }} yıl borçlu</span>
                                    @else
                                        <span class="badge-green">Güncel</span>
                                    @endif
                                </div>
                            </a>
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
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink">Yeni üye hesabı</h3>
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
                            <div>
                                <label class="field-label">Üyelik tarihi</label>
                                <input type="date" name="joined_at" value="{{ old('joined_at', now()->toDateString()) }}" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Şifre</label>
                                <input type="text" name="password" required minlength="6" class="field-input @error('password', 'create') !border-red-400 @enderror">
                                @error('password', 'create') <p class="field-error">{{ $message }}</p> @enderror
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
                        <div>
                            <label class="field-label">Adres <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                            <textarea name="address" rows="2" class="field-input">{{ old('address') }}</textarea>
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="createOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Oluştur</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
