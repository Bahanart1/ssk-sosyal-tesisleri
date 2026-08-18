<x-layouts.customer title="Hesabım">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="section-label">Üyelik</p>
            <h1 class="page-title mt-1">Hesabım</h1>
            <p class="page-subtitle">İletişim bilgilerinizi güncelleyin ve şifrenizi değiştirin.</p>
        </div>
        <img src="{{ asset('images/tesisler/gure-003.webp') }}" alt=""
             class="hidden h-20 w-36 rounded-xl object-cover sm:block" loading="lazy">
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Üyelik künyesi (salt okunur) --}}
        <div class="surface overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">Üyelik bilgileri</h2>
                <p class="text-xs text-ink-muted">Bu alanlar Dernek tarafından yönetilir.</p>
            </div>
            <div class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-3 px-5 py-2.5">
                    <span class="text-ink-muted">Ad soyad</span>
                    <span class="text-right font-medium text-ink">{{ $member->name }}</span>
                </div>
                <div class="flex justify-between gap-3 px-5 py-2.5">
                    <span class="text-ink-muted">TC kimlik no</span>
                    <span class="font-mono text-xs text-ink">{{ $member->maskedTcNo() }}</span>
                </div>
                <div class="flex justify-between gap-3 px-5 py-2.5">
                    <span class="text-ink-muted">Üyelik no</span>
                    <span class="font-medium text-ink">{{ $member->membership_no ?? '—' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-5 py-2.5">
                    <span class="text-ink-muted">Grup</span>
                    <span class="text-right font-medium text-ink">{{ $member->customerGroup?->name ?? '—' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-5 py-2.5">
                    <span class="text-ink-muted">Üyelik tarihi</span>
                    <span class="font-medium text-ink">{{ $member->joined_at?->translatedFormat('d F Y') ?? '—' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-5 py-2.5">
                    <span class="text-ink-muted">Aidat</span>
                    @if (! $member->isMember())
                        <span class="font-medium text-ink-muted">Muaf</span>
                    @else
                        <a href="{{ route('customer.dues.index') }}"
                           class="font-medium {{ $member->hasDuesDebt() ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }} hover:underline">
                            {{ $member->hasDuesDebt() ? 'Borçlu' : 'Güncel' }} →
                        </a>
                    @endif
                </div>
            </div>
            <p class="border-t border-line bg-surface-alt px-5 py-3 text-xs text-ink-muted">
                Ad soyad, TC kimlik numarası veya grup bilginizde bir hata varsa Dernek ile iletişime geçin.
            </p>
        </div>

        <div class="space-y-6 lg:col-span-2">
            {{-- İletişim bilgileri --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">İletişim bilgileri</h2>
                    <p class="text-xs text-ink-muted">Yer tahsisi bildirimleri bu bilgilere gönderilir.</p>
                </div>
                <form method="POST" action="{{ route('customer.profile.update') }}" class="space-y-4 p-5">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Telefon</label>
                            <input type="text" name="phone" value="{{ old('phone', $member->phone) }}"
                                   class="field-input @error('phone', 'profile') !border-red-400 @enderror">
                            @error('phone', 'profile') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">E-posta</label>
                            <input type="email" name="email" value="{{ old('email', $member->email) }}"
                                   class="field-input @error('email', 'profile') !border-red-400 @enderror">
                            @error('email', 'profile') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Adres</label>
                        <textarea name="address" rows="3" class="field-input">{{ old('address', $member->address) }}</textarea>
                        @error('address', 'profile') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Bilgileri Kaydet</button>
                    </div>
                </form>
            </div>

            {{-- Şifre --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">Şifre değiştir</h2>
                    <p class="text-xs text-ink-muted">En az 8 karakter olmalıdır.</p>
                </div>
                <form method="POST" action="{{ route('customer.profile.password') }}" class="space-y-4 p-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="field-label">Mevcut şifreniz</label>
                        <input type="password" name="current_password" required autocomplete="current-password"
                               class="field-input @error('current_password', 'password') !border-red-400 @enderror">
                        @error('current_password', 'password') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Yeni şifre</label>
                            <input type="password" name="password" required minlength="8" autocomplete="new-password"
                                   class="field-input @error('password', 'password') !border-red-400 @enderror">
                            @error('password', 'password') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label">Yeni şifre (tekrar)</label>
                            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="field-input">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Şifreyi Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.customer>
