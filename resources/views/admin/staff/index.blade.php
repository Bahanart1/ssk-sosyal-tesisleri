<x-layouts.admin title="Yöneticiler">

    <div x-data="{ editing: {}, yeniAcik: false }">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="section-label">Sistem</p>
                <h1 class="page-title mt-1">Yöneticiler ve yetkiler</h1>
                <p class="page-subtitle">Panele girecek hesapları tanımlayın; çalışan rolünün neleri yapabileceğini buradan belirleyin.</p>
            </div>
            <button type="button" @click="yeniAcik = ! yeniAcik" class="btn-primary">Yönetici ekle</button>
        </div>

        {{-- Yeni yönetici --}}
        <div class="surface mb-6 overflow-hidden" x-show="yeniAcik" x-cloak>
            <form method="POST" action="{{ route('admin.staff.store') }}" class="grid gap-4 p-6 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="field-label">Ad soyad</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="field-input">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">E-posta</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="field-input">
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">Şifre</label>
                    <input type="password" name="password" required minlength="8" class="field-input">
                    <p class="field-hint">En az 8 karakter. Hesabı devrederken kullanıcıya kendisi değiştirtin.</p>
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">Rol</label>
                    <select name="role" required class="field-input">
                        @foreach ($roles as $deger => $ad)
                            <option value="{{ $deger }}" @selected(old('role') === $deger)>{{ $ad }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2"><button class="btn-primary">Ekle</button></div>
            </form>
        </div>

        {{-- Mevcut yöneticiler --}}
        <div class="surface mb-8 overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Ad soyad</th><th>E-posta</th><th>Rol</th><th>Durum</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($staff as $kisi)
                        @php $rol = $kisi->roles->first()?->name; @endphp
                        <tr>
                            <td class="font-medium">{{ $kisi->name }}</td>
                            <td class="text-xs text-ink-muted">{{ $kisi->email }}</td>
                            <td>
                                @if ($rol === 'super-admin')
                                    <span class="badge-accent">Super Admin</span>
                                @elseif ($rol)
                                    <span class="badge-teal">Çalışan</span>
                                @else
                                    <span class="badge-amber">Rol atanmamış</span>
                                @endif
                            </td>
                            <td><span class="badge-{{ $kisi->is_active ? 'green' : 'gray' }}">{{ $kisi->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                            <td class="text-right">
                                <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs"
                                        @click="editing = {{ Illuminate\Support\Js::from([
                                            'id' => $kisi->id,
                                            'name' => $kisi->name,
                                            'role' => $rol ?? 'calisan',
                                            'is_active' => $kisi->is_active,
                                        ]) }}">Düzenle</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-ink-muted">Tanımlı yönetici yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Çalışan rolünün yetkileri --}}
        <div class="surface overflow-hidden">
            <div class="border-b border-line px-6 py-4">
                <h2 class="font-display text-lg font-semibold text-ink">Çalışan rolünün yetkileri</h2>
                <p class="mt-1 text-xs text-ink-muted">
                    İşaretlenen işlemleri çalışan yapabilir. Super admin bu listeden bağımsız olarak her şeyi yapar.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.staff.permissions') }}" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissionGroups as $bolum => $yetkiler)
                        <div>
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-ink-subtle">{{ $bolum }}</p>
                            <div class="space-y-1.5">
                                @foreach ($yetkiler as $yetki => $ad)
                                    <label class="flex cursor-pointer items-start gap-2 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $yetki }}"
                                               @checked(in_array($yetki, $staffPermissions, true))
                                               class="mt-0.5 rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                                        <span class="text-ink-muted">{{ $ad }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 border-t border-line pt-4">
                    <button class="btn-primary">Yetkileri kaydet</button>
                </div>
            </form>
        </div>

        {{-- Düzenleme modalı --}}
        <template x-teleport="body">
            <div x-show="editing.id" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editing = {}"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink" x-text="editing?.name"></h3>
                    <form method="POST" :action="'{{ url('admin/yoneticiler') }}/' + editing?.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="field-label">Rol</label>
                            <select name="role" x-model="editing.role" class="field-input">
                                @foreach ($roles as $deger => $ad)
                                    <option value="{{ $deger }}">{{ $ad }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="editing.is_active"
                                   class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            Hesap aktif
                        </label>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="editing = {}" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

</x-layouts.admin>
